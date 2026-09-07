<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly RequestActionApproval $requestApproval) {}

    public function index(): View
    {
        return view('expenses.index', [
            'expenses' => Expense::query()->with(['category', 'user'])->latest('expense_date')->paginate(20),
            'categories' => ExpenseCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:1000'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,transfer,qris,ewallet'],
        ]);

        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::FinancialExpense,
            null,
            ['data' => $data],
            $data['description'],
        );

        return back()->with('status', "Pengeluaran menunggu persetujuan ({$approval->request_number}).");
    }
}
