<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            // Pastikan set FRONTEND_URL di file .env
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $url = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->subject('Permintaan Reset Password (Aplikasi Absensi)') 
                ->line('Halo!') 
                ->line('Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.')
                ->action('Reset Password', $url) 
                ->line('Link reset password ini akan kedaluwarsa dalam 60 menit.')
                ->line('Jika Anda tidak meminta reset password, abaikan email ini.')
                ->salutation('Hormat kami, Tim Absensi');
        });
    }
}
