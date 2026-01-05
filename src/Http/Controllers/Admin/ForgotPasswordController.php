<?php

namespace A17\Twill\Http\Controllers\Admin;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\Factory as ViewFactory;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
     */
    use SendsPasswordResetEmails;

    /**
     * @var PasswordBrokerManager
     */
    protected $passwordBrokerManager;

    public function __construct(PasswordBrokerManager $passwordBrokerManager)
    {
        parent::__construct();

        $this->passwordBrokerManager = $passwordBrokerManager;
        $this->middleware('twill_guest');
    }

    /**
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return $this->passwordBrokerManager->broker('twill_users');
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showLinkRequestForm(ViewFactory $viewFactory)
    {
        return $viewFactory->make('twill::auth.passwords.email');
    }

    /**
     * Send a reset link to the given user with case-insensitive email lookup.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        // Find user with case-insensitive email lookup (via scopeWhereEmail override)
        $user = twillModel('user')::whereEmail($request->input('email'))->first();

        if (! $user) {
            return $this->sendResetLinkFailedResponse($request, Password::INVALID_USER);
        }

        // Create token and send notification using the user's actual email
        $token = $this->broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }
}
