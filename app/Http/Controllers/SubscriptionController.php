<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Show available plans.
     */
    public function plans()
    {
        $plans = Plan::orderBy('price')->get();

        return view('subscription.plans', compact('plans'));
    }

    /**
     * Show the checkout page for a specific plan.
     */
    public function checkout(Plan $plan)
    {
        $user = auth()->user();
        $intent = $user->createSetupIntent();

        return view('subscription.checkout', compact('plan', 'intent'));
    }

    /**
     * Process the subscription checkout.
     */
    public function subscribe(Request $request, Plan $plan)
    {
        $user = $request->user();

        $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $user->createOrGetStripeCustomer();
        $user->updateDefaultPaymentMethod($request->payment_method);

        $subscription = $user->newSubscription('default', $plan->stripe_price_id ?? null)
            ->create($request->payment_method);

        // Store subscription record
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_id' => $subscription->stripe_id ?? '',
            'stripe_status' => $subscription->stripe_status ?? 'active',
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Successfully subscribed to the ' . $plan->name . ' plan!');
    }

    /**
     * Show the billing portal / manage subscription page.
     */
    public function manage(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->latest()->first();

        return view('subscription.manage', compact('subscription'));
    }

    /**
     * Redirect to Stripe billing portal.
     */
    public function billingPortal(Request $request)
    {
        $user = $request->user();

        return $user->redirectToBillingPortal(route('subscription.manage'));
    }

    /**
     * Cancel the current subscription.
     */
    public function cancel(Request $request)
    {
        $user = $request->user();

        if ($user->subscription()) {
            $user->subscription()->cancel();
        }

        return redirect()->route('subscription.manage')
            ->with('success', 'Your subscription has been cancelled.');
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request)
    {
        $user = $request->user();

        if ($user->subscription() && $user->subscription()->onGracePeriod()) {
            $user->subscription()->resume();
        }

        return redirect()->route('subscription.manage')
            ->with('success', 'Your subscription has been resumed.');
    }
}
