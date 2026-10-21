@extends('errors.layout')

@section('status', '419')
@section('title', 'La sesión de la página venció')
@section('message', 'Por seguridad, la solicitud ya no es válida. Vuelva a ingresar al sistema e intente nuevamente.')
@section('guidance', 'Antes de repetir una operación, compruebe si el cambio ya se realizó. Los datos que no llegó a guardar podrían necesitar ingresarse nuevamente.')
