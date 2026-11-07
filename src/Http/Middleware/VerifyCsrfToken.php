<?php

namespace Spark\Pulse\Http\Middleware;

use Spark\Foundation\Http\Middlewares\CsrfProtection;

/** Protect Pulse actions even when the host has no global web middleware. */
final class VerifyCsrfToken extends CsrfProtection
{
}
