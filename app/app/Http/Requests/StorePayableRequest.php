<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Payable;
use App\Services\FinanceiroDepartmentScope;
use App\Services\PayableBranchScope;
use App\Support\PayableEmpresaExclusion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->hasPermission('*') || $user->hasPermission('financeiro.contas_pagar.lancar'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title_number' => ['nullable', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:120'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_cnpj' => ['nullable', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'due_date' => ['nullable', 'date'],
            'issue_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'requester_comment' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:120'],
            'filial' => ['required', 'string', 'regex:/^\d+-\d+$/'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'codfor' => ['nullable', 'integer', 'min:1'],
            'codntg' => ['nullable', 'integer', 'min:0'],
            'codccu' => ['nullable', 'string', 'max:40'],
            'ctafin' => ['nullable', 'integer', 'min:0'],
            'ctared' => ['nullable', 'integer', 'min:0'],
            'codtns' => ['nullable', 'string', 'max:20'],
            'codtpt' => ['nullable', 'string', 'max:10'],
            'payment_priority' => ['nullable', Rule::in(Payable::PRIORITY_VALUES)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title_number' => 'número do título',
            'nickname' => 'apelido',
            'supplier_name' => 'fornecedor',
            'supplier_cnpj' => 'CNPJ',
            'amount' => 'valor',
            'due_date' => 'vencimento',
            'issue_date' => 'emissão',
            'description' => 'observação',
            'requester_comment' => 'comentário do solicitante',
            'category' => 'categoria',
            'filial' => 'filial',
            'department_id' => 'departamento',
            'codfor' => 'código do fornecedor',
            'codntg' => 'natureza de gasto',
            'codccu' => 'centro de custo',
            'ctafin' => 'conta financeira',
            'ctared' => 'conta reduzida',
            'codtns' => 'código de transação',
            'codtpt' => 'tipo de título',
            'payment_priority' => 'prioridade',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_name.required' => 'Informe o nome do fornecedor.',
            'amount.required' => 'Informe o valor do título.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'filial.required' => 'Selecione a filial do título.',
            'filial.regex' => 'Filial inválida. Use o formato empresa-filial.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $pair = $this->parsedFilial();
            if (! $pair) {
                $validator->errors()->add('filial', 'Filial inválida.');

                return;
            }

            [$codemp, $codfil] = $pair;

            if (PayableEmpresaExclusion::isExcluded($codemp)) {
                $validator->errors()->add('filial', 'Esta empresa não participa do fluxo de Contas a Pagar.');

                return;
            }

            $scope = app(PayableBranchScope::class);
            if (! $scope->canBypass($user)) {
                $allowed = collect($scope->filialOptionsForUser($user))
                    ->contains(fn (array $opt) => (int) $opt['codemp'] === $codemp && (int) $opt['codfil'] === $codfil);

                if (! $allowed) {
                    $validator->errors()->add('filial', 'Você não tem acesso a esta filial.');
                }
            }

            $deptScope = app(FinanceiroDepartmentScope::class);
            $departmentId = $this->input('department_id');

            if (! $deptScope->canBypass($user)) {
                $locked = $deptScope->resolve($user);
                if ($locked === null) {
                    $validator->errors()->add('department_id', 'Seu usuário não possui departamento vinculado.');
                } elseif ($departmentId !== null && (int) $departmentId !== $locked) {
                    $validator->errors()->add('department_id', 'Você só pode lançar títulos no seu departamento.');
                }
            } elseif ($departmentId) {
                $active = Department::whereKey((int) $departmentId)->where('is_active', true)->exists();
                if (! $active) {
                    $validator->errors()->add('department_id', 'Departamento inválido ou inativo.');
                }
            }
        });
    }

    /** @return array{0:int,1:int}|null */
    public function parsedFilial(): ?array
    {
        $value = (string) $this->input('filial', '');
        if (! preg_match('/^(\d+)-(\d+)$/', trim($value), $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * Payload pronto para Payable::create (sem status/origem — o controller define).
     *
     * @return array<string, mixed>
     */
    public function payableAttributes(): array
    {
        [$codemp, $codfil] = $this->parsedFilial();

        $branch = Branch::query()
            ->where('is_active', true)
            ->where('cod_emp', $codemp)
            ->where('cod_fil', $codfil)
            ->first();

        $amount = round((float) $this->input('amount'), 2);
        $dueDate = $this->filled('due_date')
            ? $this->input('due_date')
            : Payable::defaultDueDate()->toDateString();
        $issueDate = $this->input('issue_date') ?: null;
        $titleNumber = filled($this->input('title_number')) ? trim((string) $this->input('title_number')) : null;
        $description = filled($this->input('description')) ? trim((string) $this->input('description')) : null;
        $codtns = filled($this->input('codtns')) ? trim((string) $this->input('codtns')) : null;
        $category = filled($this->input('category'))
            ? trim((string) $this->input('category'))
            : ($codtns ? 'Transação '.$codtns : null);

        $deptScope = app(FinanceiroDepartmentScope::class);
        $user = $this->user();
        $departmentId = $deptScope->canBypass($user)
            ? ($this->filled('department_id') ? (int) $this->input('department_id') : null)
            : $deptScope->resolve($user);

        return [
            'title_number' => $titleNumber,
            'nickname' => filled($this->input('nickname')) ? trim((string) $this->input('nickname')) : null,
            'supplier_name' => trim((string) $this->input('supplier_name')),
            'supplier_cnpj' => filled($this->input('supplier_cnpj')) ? trim((string) $this->input('supplier_cnpj')) : null,
            'amount' => $amount,
            'due_date' => $dueDate,
            'issue_date' => $issueDate,
            'description' => $description,
            'category' => $category,
            'branch_id' => $branch?->id,
            'department_id' => $departmentId,
            'payment_priority' => $this->input('payment_priority') ?: null,
            'codemp' => $codemp,
            'codfil' => $codfil,
            'codfor' => $this->filled('codfor') ? (int) $this->input('codfor') : null,
            'codntg' => $this->filled('codntg') ? (int) $this->input('codntg') : null,
            'codccu' => filled($this->input('codccu')) ? trim((string) $this->input('codccu')) : null,
            'ctafin' => $this->filled('ctafin') ? (int) $this->input('ctafin') : null,
            'ctared' => $this->filled('ctared') ? (int) $this->input('ctared') : null,
            'codtns' => $codtns,
            'codtpt' => filled($this->input('codtpt')) ? trim((string) $this->input('codtpt')) : null,
            'numtit' => $titleNumber,
            'vlrori' => $amount,
            'vlrabe' => $amount,
            'datemi' => $issueDate,
            'vctori' => $dueDate,
            'vctpro' => $dueDate,
            'obstcp' => $description,
        ];
    }
}
