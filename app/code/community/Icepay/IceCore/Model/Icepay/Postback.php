<?php

/**
 *  ICEPAY Core - Postback processing
 *  @version 1.0.1
 *  @author Olaf Abbenhuis
 *  @copyright ICEPAY <www.icepay.com>
 *  
 *  Disclaimer:
 *  The merchant is entitled to change de ICEPAY plug-in code,
 *  any changes will be at merchant's own risk.
 *  Requesting ICEPAY support for a modified plug-in will be
 *  charged in accordance with the standard ICEPAY tariffs.
 * 
 */
class Icepay_IceCore_Model_Icepay_Postback {

    protected $sqlModel;
    protected $orderModel;
    protected $order;
    protected $storeID;
    private $_post;

    public function __construct()
    {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
        $this->orderModel = Mage::getModel('sales/order');
    }

    public function handle($_vars)
    {
        if (!$_vars) {
            Mage::helper("icecore")->log("No Postback vars");
            die("ICEPAY postback page installed correctly.");
        }
        $this->_post = $_vars;

        if ($_vars['Status'] == Icepay_IceCore_Model_Config::STATUS_VERSION_CHECK) {
            $this->outputVersion($this->validateVersion());
        }

        if (!$this->checkIP($_SERVER['REMOTE_ADDR'])) {
            Mage::helper("icecore")->log(sprintf("IP not in range (%s)", $_SERVER['REMOTE_ADDR']));
            die("IP not in range");
        }
        Mage::helper("icecore")->log(serialize($_vars));

        $this->order = $this->orderModel->loadByIncrementId($_vars['OrderID']);

        $icepayTransaction = $this->sqlModel->loadPaymentByID($this->order->getRealOrderId());

        $this->storeID = $icepayTransaction["store_id"];

        $transActionID = $this->saveTransaction($_vars);

        $doSpecialActions = false;

        if ($_vars['Status'] == Icepay_IceCore_Model_Config::STATUS_AUTH) {
            if (Mage::helper('icecore')->isModuleInstalled('Icepay_AutoCapture')) {
                if (Mage::Helper('icepay_autocapture')->isAutoCaptureActive($this->storeID)) {
                    $_vars['Status'] = Icepay_IceCore_Model_Config::STATUS_SUCCESS;
                }
            }
        }

        if ($this->canUpdateBasedOnIcepayTable($icepayTransaction['status'], $_vars['Status'])) {
            /* creating the invoice causes major overhead! Status should to be updated and saved first */
            if ($_vars['Status'] == Icepay_IceCore_Model_Config::STATUS_SUCCESS)
                $doSpecialActions = true;

            // Update ICEPAY transaction info
            $newData = $icepayTransaction;
            $newData['update_time'] = now();
            $newData['status'] = $_vars['Status'];
            $newData['transaction_id'] = $_vars['PaymentID'];
            $this->sqlModel->changeStatus($newData);

            // Update order status
            if ($_vars['Status'] == Icepay_IceCore_Model_Config::STATUS_ERROR) {
                $this->order->cancel();
            } else {
                $this->order->setState(
                        $this->getMagentoState($_vars['Status']), $this->getMagentoStatus($_vars['Status']), Mage::helper('icecore')->__('Status of order changed'), true
                );
            };
        };

        $this->order->save();

        $this->sendMail($icepayTransaction['status'], $_vars['Status']);

        if ($doSpecialActions) {
            $extraMsg = $this->specialActions($_vars['Status'], $transActionID);
            $this->order->setState(
                    $this->getMagentoState($_vars['Status']), $this->getMagentoStatus($_vars['Status']), $extraMsg, false
            );
            $this->order->save();
        }
    }

    protected function outputVersion($extended = false)
    {
        $dump = array(
            "module" => $this->getVersions(),
            "notice" => "Checksum validation passed!"
        );
        if ($extended) {

            $dump["additional"] = array(
                "magento" => Mage::getVersion(),
                "soap" => Mage::helper('icecore')->hasSOAP() ? "installed" : "not installed",
                "storescope" => Mage::helper('icecore')->getStoreScopeID(),
            );
        } else {
            $dump["notice"] = "Checksum failed! Merchant ID and Secret code probably incorrect.";
        }
        var_dump($dump);
        exit();
    }

    protected function validateVersion()
    {
        if ($this->generateChecksumForVersion() != $this->_post["Checksum"])
            return false;
        return true;
    }

    protected function getVersions()
    {
        $_v = "";
        if (class_exists(Mage::getConfig()->getHelperClassName('icecore')))
            $_v.= sprintf("%s %s. ", Mage::helper('icecore')->title, Mage::helper('icecore')->version);
        if (class_exists(Mage::getConfig()->getHelperClassName('icebasic')))
            $_v.= sprintf("%s %s. ", Mage::helper('icebasic')->title, Mage::helper('icebasic')->version);
        if (class_exists(Mage::getConfig()->getHelperClassName('iceadvanced')))
            $_v.= sprintf("%s %s. ", Mage::helper('iceadvanced')->title, Mage::helper('iceadvanced')->version);
        return $_v;
    }

    protected function generateChecksumForVersion()
    {
        return sha1(
                sprintf("%s|%s|%s|%s", Mage::helper('icecore')->getConfig(Icepay_IceCore_Model_Config::SECRETCODE), Mage::helper('icecore')->getConfig(Icepay_IceCore_Model_Config::MERCHANTID), $this->_post["Status"], substr(strval(time()), 0, 8)
                )
        );
    }

    protected function sendMail($currentStatus, $newStatus)
    {
        switch ($currentStatus) {
            case Icepay_IceCore_Model_Config::STATUS_NEW:
                if ($newStatus == Icepay_IceCore_Model_Config::STATUS_ERROR) {
                    $this->order->sendOrderUpdateEmail();
                } else {
                    $this->order->sendNewOrderEmail();
                };
                break;
            default:
                $this->order->sendOrderUpdateEmail();
        }
    }

    protected function saveTransaction($_vars)
    {
        $payment = $this->order->getPayment();

        $transaction = $payment->getTransaction($_vars['PaymentID']);

        $i = 0;
        do {
            $id = $_vars['PaymentID'] . (($i > 0) ? "-{$i}" : "");
            $transaction = $payment->getTransaction($id);
            $i++;
        } while ($transaction);
        $i--;

        $id = $_vars['PaymentID'] . (($i > 0) ? "-{$i}" : "");

        $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $_vars);


        $payment->setTransactionId($id)
                ->setIsTransactionClosed($this->isClosedStatus($_vars['Status']));

        if ($this->isRefund($_vars['Status'])) {
            $payment->setParentTransactionId($this->getParentPaymentID($_vars['StatusCode']));
            //Creditmemo currently not supported
        };

        $payment->addTransaction(
                $this->getTransactionStatus($_vars['Status']), null, false);

        $payment->save();

        return $id;
    }

    protected function createInvoice($id)
    {
        $invoice = $this->order->prepareInvoice()
                ->setTransactionId($id)
                ->addComment(Mage::helper('icecore')->__('Auto-generated by ICEPAY'))
                ->register()
                ->pay();

        $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

        $transactionSave->save();

        $invoice->sendEmail();

        return $invoice;
    }

    protected function specialActions($newStatus, $transActionID)
    {
        $msg = "";
        switch ($newStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS:
                if (!$this->order->hasInvoices() && Mage::app()->getStore($this->storeID)->getConfig(Icepay_IceCore_Model_Config::AUTOINVOICE) == 1) {
                    $invoice = $this->createInvoice($transActionID);
                    $msg = Mage::helper("icecore")->__('Invoice Auto-Created: %s', '<strong>' . $invoice->getIncrementId() . '</strong>');
                };
                break;
        }
        return $msg;
    }

    protected function canUpdate($currentStatus, $newStatus)
    {
        switch ($newStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS: return ($currentStatus == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $currentStatus == Mage_Sales_Model_Order::STATE_NEW);
            case Icepay_IceCore_Model_Config::STATUS_OPEN: return ($currentStatus == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $currentStatus == Mage_Sales_Model_Order::STATE_NEW);
            case Icepay_IceCore_Model_Config::STATUS_ERROR: return ($currentStatus == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $currentStatus == Mage_Sales_Model_Order::STATE_NEW);
            case Icepay_IceCore_Model_Config::STATUS_AUTH: return ($currentStatus == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $currentStatus == Mage_Sales_Model_Order::STATE_NEW);
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK: return ($currentStatus == Mage_Sales_Model_Order::STATE_PROCESSING || $currentStatus == Mage_Sales_Model_Order::STATE_COMPLETE);
            case Icepay_IceCore_Model_Config::STATUS_REFUND: return ($currentStatus == Mage_Sales_Model_Order::STATE_PROCESSING || $currentStatus == Mage_Sales_Model_Order::STATE_COMPLETE);
            default:
                return false;
        };
    }

    protected function canUpdateBasedOnIcepayTable($currentStatus, $newStatus)
    {
        switch ($currentStatus) {
            case Icepay_IceCore_Model_Config::STATUS_NEW:
            case Icepay_IceCore_Model_Config::STATUS_OPEN:
                return (
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_SUCCESS ||
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_ERROR ||
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_AUTH ||
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_OPEN
                        );
                break;
            case Icepay_IceCore_Model_Config::STATUS_AUTH:
                return (
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_SUCCESS ||
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_ERROR
                        );
                break;
            case Icepay_IceCore_Model_Config::STATUS_ERROR:
                return (
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_SUCCESS
                        );
                break;
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS:
                return (
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_CHARGEBACK ||
                        $newStatus == Icepay_IceCore_Model_Config::STATUS_REFUND
                        );
                break;
            default:
                return false;
                break;
        }
    }

    protected function getMagentoStatus($icepayStatus)
    {
        switch ($icepayStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_SUCCESS;
            case Icepay_IceCore_Model_Config::STATUS_OPEN: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_OPEN;
            case Icepay_IceCore_Model_Config::STATUS_ERROR: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_ERROR;
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_CHARGEBACK;
            case Icepay_IceCore_Model_Config::STATUS_REFUND: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_REFUND;
            case Icepay_IceCore_Model_Config::STATUS_AUTH: return Icepay_IceCore_Model_Config::STATUS_MAGENTO_AUTHORIZED;
            default:
                return false;
        };
    }

    protected function getMagentoState($icepayStatus)
    {
        switch ($icepayStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS:
                return Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case Icepay_IceCore_Model_Config::STATUS_OPEN:
            case Icepay_IceCore_Model_config::STATUS_AUTH:
                return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                break;
            case Icepay_IceCore_Model_Config::STATUS_ERROR:
                return Mage_Sales_Model_Order::STATE_CANCELED;
                break;
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK:
            case Icepay_IceCore_Model_Config::STATUS_REFUND:
                return Mage_Sales_Model_Order::STATE_HOLDED;
                //return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                break;
            default:
                return false;
        };
    }

    protected function getTransactionStatus($icepayStatus)
    {
        switch ($icepayStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT;
            case Icepay_IceCore_Model_Config::STATUS_OPEN: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
            case Icepay_IceCore_Model_config::STATUS_AUTH: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
            case Icepay_IceCore_Model_Config::STATUS_ERROR: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
            case Icepay_IceCore_Model_Config::STATUS_REFUND: return Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
            default: return false;
        };
    }

    protected function isClosedStatus($icepayStatus)
    {
        switch ($icepayStatus) {
            case Icepay_IceCore_Model_Config::STATUS_SUCCESS: return true;
            case Icepay_IceCore_Model_Config::STATUS_OPEN: return false;
            case Icepay_IceCore_Model_config::STATUS_AUTH: return false;
            case Icepay_IceCore_Model_Config::STATUS_ERROR: return true;
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK: return true;
            case Icepay_IceCore_Model_Config::STATUS_REFUND: return true;
            default: return false;
        };
    }

    protected function isRefund($icepayStatus)
    {
        switch ($icepayStatus) {
            case Icepay_IceCore_Model_Config::STATUS_CHARGEBACK: return true;
            case Icepay_IceCore_Model_Config::STATUS_REFUND: return true;
            default:
                return false;
        };
    }

    protected function getParentPaymentID($statusString)
    {
        $arr = explode("PaymentID:", $statusString);
        return intval($arr[1]);
    }

    protected function getTransactionString($_vars)
    {
        $str = "";
        foreach ($_vars as $key => $value) {
            $str .= "{$key}: {$value}<BR>";
        }
        return $str;
    }

    protected function checkIP($remote_ip)
    {
        return true;
        $whiteList = array('194.30.175.0-194.30.175.255', '194.126.241.128-194.126.241.191');

        if (Mage::helper('icecore')->getConfig('icecore/core_options/iprange') != '') {
            $ipRanges = explode(",", Mage::helper('icecore')->getConfig('icecore/core_options/iprange'));

            foreach ($ipRanges as $ipRange) {
                $ip = explode("-", $ipRange);
                $whiteList[] = "$ip[0]-$ip[1]";
            }
        }

        foreach ($whiteList as $allowedIp) {
            if ($this->ip_in_range($remote_ip, $allowedIp))
                return true;
        }

        return false;
    }

    protected function decbin32($dec)
    {
        return str_pad(decbin($dec), 32, '0', STR_PAD_LEFT);
    }

    protected function ip_in_range($ip, $range)
    {
        if (strpos($range, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);
            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while (count($x) < 4)
                    $x[] = '0';
                list($a, $b, $c, $d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                # Strategy 1 - Using substr to chop up the range and pad it with 1s to the right
                $broadcast_dec = bindec(substr($this->decbin32($range_dec), 0, $netmask)
                        . str_pad('', 32 - $netmask, '1'));

                # Strategy 2 - Use math to OR the range with the wildcard to create the Broadcast address
                $wildcard_dec = pow(2, (32 - $netmask)) - 1;
                $broadcast_dec = $range_dec | $wildcard_dec;

                return (($ip_dec & $broadcast_dec) == $ip_dec);
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($range, '*') !== false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }

            if (strpos($range, '-') !== false) { // A-B format
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = ip2long($lower);
                $upper_dec = ip2long($upper);
                $ip_dec = ip2long($ip);
                return ( ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec) );
            }

            return false;
        }

        $ip_dec = ip2long($ip);
        return (($ip_dec & $netmask_dec) == $ip_dec);
    }

}