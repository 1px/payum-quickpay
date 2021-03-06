<?php

namespace Setono\Payum\QuickPay\Action;

use Setono\Payum\QuickPay\Action\Api\ApiAwareTrait;
use Setono\Payum\QuickPay\Model\QuickPayPayment;
use Setono\Payum\QuickPay\Model\QuickPayPaymentOperation;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class StatusAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (!$model['quickpayPaymentId']) {
            $request->markNew();
            return;
        }

        $quickpayPayment = $this->api->getPayment($model);

        switch ($quickpayPayment->getState()) {
            case QuickPayPayment::STATE_INITIAL:
                $request->markNew();
                break;
            case QuickPayPayment::STATE_NEW:
                $latestOperation = $quickpayPayment->getLatestOperation();
                if ($latestOperation && $latestOperation->getType() == QuickPayPaymentOperation::TYPE_AUTHORIZE && $latestOperation->isApproved()) {
                    $request->markAuthorized();
                } else {
                    $request->markFailed();
                }
                break;
            case QuickPayPayment::STATE_PENDING:
                $request->markPending();
                break;
            case QuickPayPayment::STATE_REJECTED:
                $request->markFailed();
                break;
            case QuickPayPayment::STATE_PROCESSED:
                $latestOperation = $quickpayPayment->getLatestOperation();
                if ($latestOperation->getType() == QuickPayPaymentOperation::TYPE_CAPTURE && $latestOperation->isApproved()) {
                    $request->markCaptured();
                } else {
                    $request->markCanceled();
                }
                break;
            default:
                $request->markUnknown();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
