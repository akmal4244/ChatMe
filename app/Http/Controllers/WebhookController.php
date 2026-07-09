<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhooks.
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing error'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe webhook event: ' . $event->type);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle subscription created event.
     */
    protected function handleSubscriptionCreated($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => $stripeSubscription->status,
            ]);
        }

        Log::info('Stripe subscription created: ' . $stripeSubscription->id);
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => $stripeSubscription->status,
            ]);
        }

        Log::info('Stripe subscription updated: ' . $stripeSubscription->id);
    }

    /**
     * Handle subscription deleted (cancelled) event.
     */
    protected function handleSubscriptionDeleted($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => 'cancelled',
            ]);
        }

        Log::info('Stripe subscription deleted: ' . $stripeSubscription->id);
    }

    /**
     * Handle successful payment event.
     */
    protected function handlePaymentSucceeded($invoice)
    {
        $stripeSubscriptionId = $invoice->subscription ?? null;

        if ($stripeSubscriptionId) {
            $subscription = Subscription::where('stripe_id', $stripeSubscriptionId)->first();
            if ($subscription) {
                $subscription->update(['stripe_status' => 'active']);
            }
        }

        Log::info('Stripe payment succeeded for invoice: ' . $invoice->id);
    }

    /**
     * Handle failed payment event.
     */
    protected function handlePaymentFailed($invoice)
    {
        $stripeSubscriptionId = $invoice->subscription ?? null;

        if ($stripeSubscriptionId) {
            $subscription = Subscription::where('stripe_id', $stripeSubscriptionId)->first();
            if ($subscription) {
                $subscription->update(['stripe_status' => 'past_due']);
            }
        }

        Log::warning('Stripe payment failed for invoice: ' . $invoice->id);
    }
}
