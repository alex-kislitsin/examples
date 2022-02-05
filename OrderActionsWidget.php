<?php


namespace frontend\widgets;


use common\models\Orders;
use common\models\Service;
use common\models\Statuses;
use common\models\User;
use common\services\SumServiceOrderServices;
use frontend\modules\chat\models\Chats;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;

class OrderActionsWidget extends Widget
{
    public bool $wrap = false;
    public string $wrapperClass = 'order-progress-section';
    public string $defaultView = 'default';
    public ?Orders $order;
    public ?Service $service;
    public int $chatId = 0;
    public float $totalSum = 0;
    public int  $currencyId = 0;
    public int $status = 0;
    public ?User $user = null;
    public bool $imIsCreator = false;

    public ?bool $lastBotId = null;
    private string $viewsFolder = 'order-actions/';
    private ?Chats $chat = null;
    /**
     * @var SumServiceOrderServices
     */
    private SumServiceOrderServices $sumService;

    /**
     * @var int
     */

    public function init()
    {
        parent::init();
        if ($this->chatId > 0) {
            $this->chat = Chats::findOne($this->chatId);
        } elseif ($this->order && $this->order->chat_id > 0) {
            $this->chat = $this->order->chat;
        } else {
            $this->chat = new Chats(['status_id' => 1]);
        }

        $this->sumService = new SumServiceOrderServices($this->service, $this->order, $this->chat, $this->imIsCreator);

        if (!$this->status) {
            $this->status = $this->chat->status_id;
        }

        if (!$this->user) {
            $this->user = Yii::$app->user ? Yii::$app->user->getIdentity() : null;
        }

        $this->totalSum = $this->sumService->getSumTotal();
        $this->currencyId = $this->sumService->getCurrencyId();

        /**
         * @todo добавить addServices
         */
        /* if (!$this->totalSum && $this->order && $this->service) {
             $this->totalSum = $this->order->getClientTotalCost();
 //            $this->totalSum = $this->order->getClientTotalCost();
             if ($addServices = $this->order->addServices) {
                 foreach ($addServices as $addService) {
                     $this->totalSum += $addService->cost;
                 }
             }
         }*/
    }

    final public function run(): string
    {
        $view = $this->getViewName();
        return $this->wrap($this->render($this->viewsFolder . $view, [
            'service' => $this->service,
            'order' => $this->order,
            'chat' => $this->chat,
            'totalSum' => $this->totalSum,
            'currency_id' => $this->currencyId,
            'lang' => $this->user ? $this->user->userProfile->locale : Yii::$app->language,
        ]));
    }

    private function getViewName(): string
    {
        if ($this->canPay()) {
            return $this->defaultView;
        }

        $role = $this->userCan(User::ROLE_CREATOR) ? 'creator' : 'client';
        $view = $this->getViewByStatus($this->status);

        return $view ? "{$role}/{$view}" : $this->defaultView;
    }

    final public function canPay(): bool
    {
        return !(
            ($this->order
                && $this->order->status_id > Statuses::ORDER_STATUS_NOT_PAID)
            || !$this->user
            || !$this->userCan(User::ROLE_CLIENT)
        );
    }

    private function userCan(string $role): string
    {
        $userRoles = ($this->user) ? Yii::$app->authManager->getRolesByUser($this->user->id) : ['guest'];
        return in_array($role, array_keys($userRoles));
    }

    private function getViewByStatus(int $status): string
    {
        switch ($status) {
            case Statuses::ORDER_STATUS_PAID:
                return 'paid';
                break;

            case Statuses::ORDER_STATUS_WORK:
                return 'in-work';
                break;

            case Statuses::ORDER_STATUS_REWORK:
                return 're-work';
                break;

            case Statuses::ORDER_STATUS_COMPLETED_NEED_CHECK:
                return 'need-check';
                break;

            case Statuses::ORDER_STATUS_ACCEPT_NEED_REVIEWS:
                return 'need-reviews';
                break;

            case Statuses::ORDER_STATUS_COMPLETED:
                return 'completed';
                break;

            case Statuses::ORDER_STATUS_CANCEL:
                return 'cancel';
                break;
            case Statuses::ORDER_STATUS_CANCEL_REFUND_MONEY:
                return 'refund';
                break;

        }
        return '';
    }

    private function wrap(string $content): string
    {
        return $this->wrap ? Html::tag('div', $content, ['class' => 'order-actions-widget ' . $this->wrapperClass]) : $content;
    }

    final public function addServicesButton(): string
    {
        $button = Html::button(
            Yii::t('frontend', 'Additional services'),
            [
                'class' => 'button hollow secondary add-services-action block',
                'data-show-add-service' => true,
            ]);
        return '';
//        return ($this->service->addServices) ? $button : '';
    }

    final public function payButton(): string
    {
        if (!$this->canPay()) {
            return '';
        }
        $text = Yii::t('frontend', 'Pay')
            . ' '
            . Yii::$app->currency->getFormattedSum(
                $this->totalSum,
                $this->currencyId,
                0,
                ['span', ['data-chat-sum-total' => '']]
            );
        return Html::button($text, [
            'data' => [
                'order-pay-button' => true,
                'service-id' => $this->service->id,
                'chat-id' => $this->chat->id,
                'open' => $this->user->isActive() ? 'payment' : 'ActivationMessage'
            ],
            'class' => 'button button-payment block'
        ]);
    }

    final public function showActions(): bool
    {
        return is_null($this->lastBotId) ? 1 : $this->lastBotId;
    }
}