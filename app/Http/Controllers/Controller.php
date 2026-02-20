<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Payment Gateway API",
    description: "Payment Gateway API for processing payments, managing refunds, and handling webhooks"
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "apiKey",
    type: "http",
    scheme: "bearer",
    bearerFormat: "API Key"
)]
abstract class Controller
{
    //
}
