<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Vault\Observer;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/**
 * Order payment after save observer for storing payment vault record in db
 */
class AfterPaymentSaveObserver implements ObserverInterface
{
    const PAYMENT_OBJECT_DATA_KEY = 'payment';

    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->encryptor = $encryptor;
    }

    /**
     * Create payment vault record
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var OrderPaymentInterface $payment */
        $payment = $observer->getDataByKey(self::PAYMENT_OBJECT_DATA_KEY);
        $extensionAttributes = $payment->getExtensionAttributes();

        $paymentToken = $this->getPaymentToken($extensionAttributes);

        if ($paymentToken === null) {
            return $this;
        }

        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
        $paymentToken->setIsActive(true);

        $this->paymentTokenManagement->saveTokenWithPaymentLink($paymentToken, $payment);
        $extensionAttributes->setVaultPaymentToken($paymentToken);

        return $this;
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    protected function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getCustomerId()
            . $paymentToken->getGatewayToken()
            . $paymentToken->getPaymentMethodCode();
        return $this->encryptor->getHash($hashKey);
    }

    /**
     * Reads Payment token from Order Payment
     *
     * @param OrderPaymentExtensionInterface | null $extensionAttributes
     * @return PaymentTokenInterface | null
     */
    protected function getPaymentToken(OrderPaymentExtensionInterface $extensionAttributes = null)
    {
        if (null === $extensionAttributes) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        if (null === $paymentToken || empty($paymentToken->getGatewayToken())) {
            return null;
        }

        return $paymentToken;
    }
}
