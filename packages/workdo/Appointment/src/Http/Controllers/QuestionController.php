<?php

namespace Workdo\Appointment\Http\Controllers;

use Workdo\Appointment\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Appointment\Http\Requests\StoreQuestionRequest;
use Workdo\Appointment\Http\Requests\UpdateQuestionRequest;
use Workdo\Appointment\Events\CreateQuestion;
use Workdo\Appointment\Events\UpdateQuestion;
use Workdo\Appointment\Events\DestroyQuestion;


class QuestionController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-questions')) {
            $questions = Question::query()

                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-questions')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-questions')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('question_name'), function ($q) {
                    $q->where(function ($query) {
                        $query->where('question_name', 'like', '%' . request('question_name') . '%');
                    });
                })
                ->when(request('question_type') !== null && request('question_type') !== '', fn($q) => $q->where('question_type', request('question_type')))
                ->when(request('required_answer') !== null && request('required_answer') !== '', fn($q) => $q->where('required_answer', request('required_answer') === '1'))
                ->when(request('enabled') !== null && request('enabled') !== '', fn($q) => $q->where('enabled', request('enabled') === '1'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Appointment/Questions/Index', [
                'questions' => $questions,

            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreQuestionRequest $request)
    {
        if (Auth::user()->can('create-questions')) {
            $validated = $request->validated();

            $validated['required_answer'] = $request->boolean('required_answer', false);
            $validated['enabled'] = $request->boolean('enabled', false);

            $question = new Question();
            $question->question_name = $validated['question_name'];
            $question->question_type = $validated['question_type'];
            $question->available_answers = $validated['available_answers'] ?? '[]';
            $question->required_answer = $validated['required_answer'];
            $question->enabled = $validated['enabled'];

            $question->creator_id = Auth::id();
            $question->created_by = creatorId();
            $question->save();

            // Dispatch event for packages to handle their fields
            CreateQuestion::dispatch($request, $question);

            return redirect()->route('appointment.questions.index')->with('success', __('The question has been created successfully.'));
        } else {
            return redirect()->route('appointment.questions.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateQuestionRequest $request, Question $question)
    {
        if (Auth::user()->can('edit-questions')) {
            $validated = $request->validated();

            $validated['required_answer'] = $request->boolean('required_answer', false);
            $validated['enabled'] = $request->boolean('enabled', false);

            $question->question_name = $validated['question_name'];
            $question->question_type = $validated['question_type'];
            $question->available_answers = $validated['available_answers'] ?? '[]';
            $question->required_answer = $validated['required_answer'];
            $question->enabled = $validated['enabled'];

            $question->save();

            // Dispatch event for packages to handle their fields
            UpdateQuestion::dispatch($request, $question);

            return redirect()->back()->with('success', __('The question details are updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(Question $question)
    {
        if (Auth::user()->can('delete-questions')) {
            DestroyQuestion::dispatch($question);

            $question->delete();

            return redirect()->route('appointment.questions.index')->with('success', __('The question has been deleted.'));
        } else {
            return redirect()->route('appointment.questions.index')->with('error', __('Permission denied'));
        }
    }

    public function api()
    {
        if (Auth::user()->can('manage-questions')) {
            try {
                $questions = Question::where('created_by', creatorId())->where('creator_id', Auth::id())
                    ->where('enabled', true)
                    ->get(['id', 'question_name', 'required_answer']);

                return response()->json($questions);
            } catch (\Exception $e) {
                return response()->json(['error' => __('Failed to fetch questions')], 500);
            }
        } else {
            return response()->json(['error' => __('Permission denied')], 403);
        }
    }
}
