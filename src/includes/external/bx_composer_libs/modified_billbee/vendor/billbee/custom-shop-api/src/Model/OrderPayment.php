<?php
/**
 * This file is part of the Billbee Custom Shop API package.
 *
 * Copyright 2019-2022 by Billbee GmbH
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 *
 * Created by Julian Finkler <julian@mintware.de>
 */

namespace Billbee\CustomShopApi\Model;

use DateTimeInterface;
use JMS\Serializer\Annotation as Serializer;

class OrderPayment
{
    /**
     * @Serializer\SerializedName("transaction_id")
     * @Serializer\Type("string")
     */
    public ?string $transactionId = null;

    /**
     * @Serializer\SerializedName("pay_date")
     * @Serializer\Type("DateTime")
     */
    public ?DateTimeInterface $payDate = null;

    /**
     * @Serializer\SerializedName("payment_type")
     * @Serializer\Type("int")
     */
    public ?int $paymentType = null;

    /**
     * @Serializer\SerializedName("source_technology")
     * @Serializer\Type("string")
     */
    public ?string $sourceTechnology = null;

    /**
     * @Serializer\SerializedName("source_text")
     * @Serializer\Type("string")
     */
    public ?string $sourceText = null;

    /**
     * @Serializer\SerializedName("pay_value")
     * @Serializer\Type("float")
     */
    public ?float $payValue = null;

    /**
     * @Serializer\SerializedName("purpose")
     * @Serializer\Type("string")
     */
    public ?string $purpose = null;

    /**
     * @Serializer\SerializedName("name")
     * @Serializer\Type("string")
     */
    public ?string $name = null;

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): OrderPayment
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getPayDate(): ?DateTimeInterface
    {
        return $this->payDate;
    }

    public function setPayDate(?DateTimeInterface $payDate): OrderPayment
    {
        $this->payDate = $payDate;
        return $this;
    }

    public function getPaymentType(): ?int
    {
        return $this->paymentType;
    }

    public function setPaymentType(?int $paymentType): OrderPayment
    {
        $this->paymentType = $paymentType;
        return $this;
    }

    public function getSourceTechnology(): ?string
    {
        return $this->sourceTechnology;
    }

    public function setSourceTechnology(?string $sourceTechnology): OrderPayment
    {
        $this->sourceTechnology = $sourceTechnology;
        return $this;
    }

    public function getSourceText(): ?string
    {
        return $this->sourceText;
    }

    public function setSourceText(?string $sourceText): OrderPayment
    {
        $this->sourceText = $sourceText;
        return $this;
    }

    public function getPayValue(): ?float
    {
        return $this->payValue;
    }

    public function setPayValue(?float $payValue): OrderPayment
    {
        $this->payValue = $payValue;
        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): OrderPayment
    {
        $this->purpose = $purpose;
        return $this;
    }
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): OrderPayment
    {
        $this->name = $name;
        return $this;
    }
}