// Application/Finance — espelha app/Application/Finance/FinancialService.php
// Valida e delega a persistência ao repositório ativo (Supabase ou local).
import { store } from '../../infrastructure/persistence/Store.js';
import { Validator } from '../../core/Validator.js';
import { FINANCIAL_CATEGORY_LABELS } from '../../domain/finance/FinancialCategory.js';

export class FinancialService {
    constructor(repository = store) {
        this.repository = repository;
    }

    /** Regra equivalente a register() do PHP: valor = quantidade × valor unitário. */
    async register(input) {
        const projectId = Validator.id(input.obra_id);
        const quantity = Validator.positiveNumber(input.quantidade, 'a quantidade');
        const unitCost = Validator.nonNegativeNumber(input.valor_unitario, 'o valor unitário');
        const category = Validator.oneOf(input.categoria, Object.keys(FINANCIAL_CATEGORY_LABELS), 'Categoria');
        const description = Validator.requiredText(input.descricao, 'a descrição');
        const date = Validator.date(input.data_lancamento);

        await this.repository.addTransaction({
            obraId: projectId, category, description, quantity, unitCost, date,
        });
    }

    /** Regra equivalente a setBudget() do PHP. */
    async setBudget(input) {
        const projectId = Validator.id(input.obra_id);
        const budget = Validator.nonNegativeNumber(input.valor_orcado, 'o orçamento');
        await this.repository.setBudget(projectId, budget);
    }

    entryValue(transaction) {
        return transaction.quantity * transaction.unitCost;
    }

    totalSpent() {
        return this.repository.state.transactions
            .reduce((sum, item) => sum + this.entryValue(item), 0);
    }

    totalBudget() {
        return this.repository.state.obras.reduce((sum, item) => sum + item.budget, 0);
    }

    async remove(transactionId) {
        await this.repository.deleteTransaction(transactionId);
    }
}
