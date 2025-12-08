<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * Crear una nueva instancia del mensaje.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Construir el mensaje.
     */
    public function build()
    {
        return $this
            ->subject('¡Te damos la bienvenida a CinemaClub!')
            ->view('emails.welcome_user');
    }
}

