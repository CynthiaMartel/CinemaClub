@php
    $name = $user->name ?? 'cinéfila';
@endphp

@component('mail::message')

# Hola {{ $name }},

Gracias por registrarte en **FilmoClub**. ¡Nos alegra mucho que formes parte de nuestra comunidad cinéfila!

Aquí podrás:

- Guardar las películas que ves.
- Puntuar y escribir reseñas.
- Crear listas temáticas.
- Participar en el FilmoClub y debatir con otras personas amantes del cine.

> **Watch, Rate, Debate.**

— El equipo de FilmoClub

@endcomponent
