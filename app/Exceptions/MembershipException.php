<?php

namespace App\Exceptions;

use Exception;

class MembershipException extends Exception
{
    protected $code = 400;

    public function __construct($message = "Membership operation failed", $code = 400, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => false,
                'message' => $this->getMessage(),
                'error_type' => 'MembershipException'
            ], $this->code);
        }

        return redirect()->back()
            ->withErrors(['error' => $this->getMessage()])
            ->withInput();
    }
}
