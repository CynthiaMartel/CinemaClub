@php
    $name = $user->name ?? 'cinéfila';
@endphp

@component('mail::message')

# Hola {{ $name }},

Gracias por registrarte en **CinemaClub**. Solo falta un paso: confirmar que este email te pertenece.

@component('mail::button', ['url' => $verificationUrl, 'color' => 'blue'])
Verificar mi cuenta
@endcomponent

Este enlace expirará en **24 horas**.

Si no creaste esta cuenta, puedes ignorar este correo.

— El equipo de CinemaClub

@endcomponent
