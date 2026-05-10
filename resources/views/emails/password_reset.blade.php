@component('mail::message')

# Hola {{ $userName }},

Recibimos una solicitud para restablecer tu contraseña en **CinemaClub**.

@component('mail::button', ['url' => $resetUrl, 'color' => 'blue'])
Restablecer contraseña
@endcomponent

Este enlace expirará en **1 hora**.

Si no solicitaste esto, ignora este correo. Tu contraseña no cambiará.

— El equipo de CinemaClub

@endcomponent
