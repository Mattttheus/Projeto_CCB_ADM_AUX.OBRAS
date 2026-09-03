// Application/Finance — espelha app/Application/Finance/FinancialService.php
import { store } from '../../infrastructure/persistence/LocalStore.js';
import { Validator } from '../../core/Validator.js';
import { FINANCIAL_CATEGORY_LABELS } from '../../domain/finance/FinancialCategory.js';

export class FinancialService {
    constructor(repository = store) {
        this.repository = repository;
    }

    /** Regra equivalente a register() do PHP: valor = quantidade × valor unitário. */
    register(input) {
        const projectId = Validator.id(input.obra_id);
        const quantity = Validator.positiveNumber(input.quantidade, 'a quantidade');
        const unitCost = Validator.nonNegativeNumber(input.valor_unitario, 'o valor unitário');
        const category = Validator.oneOf(input.categoria, Object.keys(FINANCIAL_CATEGORY_LABELS), 'Categoria');
        const description = Validator.requiredText(input.descricao, 'a descrição');
        const date = Validator.date(input.data_lancamento);

        this.repository.state.transactions.push({
            id: this.repository.nextId(this.repository.state.transactions),
            obraId: projectId,
            category,
            description,
            quantity,
            unitCost,
            date,
        });
        this.repository.save();
        this.repository.log(`Lançamento financeiro "${description}" registrado.`);
    }

    /** Regra equivalente a setBudget() do PHP. */
    setBudget(input) {
        const projectId = Validator.id(input.obra_id);
        const budget = Validator.nonNegativeNumber(input.valor_orcado, 'o orçamento');
        const obra = this.repository.state.obras.find(item => item.id === projectId);
        if (!obra) {
            throw new Error('Obra não encontrada.');
        }
        obra.budget = budget;
        this.repository.save();
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

    remove(transactionId) {
        this.repository.state.transactions = this.repository.state.transactions
            .filter(item => item.id !== Number(transactionId));
        this.repository.save();
    }
}
