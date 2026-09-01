<?php

namespace App\Http\Controllers;

use App\Models\PrototypeFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'category' => ['required', 'in:usability,bug,idea,other'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:255'],
        ]);
        PrototypeFeedback::create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Feedback registrado. Obrigado por ajudar a evoluir o protótipo.');
    }
}
