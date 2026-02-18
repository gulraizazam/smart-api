<?php

namespace App\Exceptions;

use Exception;

class MembershipCodeException extends Exception
{
    protected $code = 400;

    public function __construct($message = "Membership code operation failed", $code = 400, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => false,
                'message' => $this->getMessage(),
                'error_type' => 'MembershipCodeException'
            ], $this->code);
        }

        return redirect()->back()
            ->withErrors(['error' => $this->getMessage()])
            ->withInput();
    }
}
