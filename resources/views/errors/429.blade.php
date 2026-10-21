@extends('errors.layout')

@section('status', '429')
@section('title', 'Demasiadas solicitudes')
@section('message', 'Se realizaron demasiadas solicitudes en poco tiempo. Espere un momento antes de volver a intentarlo.')
@section('guidance', 'No es necesario pulsar el botón varias veces. Espere y vuelva a realizar la acción una sola vez.')
