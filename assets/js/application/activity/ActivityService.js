// Application/Activity — espelha app/Application/Activity/ActivityService.php
// Valida e delega a persistência ao repositório ativo (Supabase ou local).
import { store } from '../../infrastructure/persistence/Store.js';
import { Validator } from '../../core/Validator.js';
import { ActivityStatus, isValidStatus, nextStatus } from '../../domain/activity/ActivityStatus.js';

export class ActivityService {
    constructor(repository = store) {
        this.activities = repository;
    }

    /** Regra equivalente a createProjectActivity() do PHP. */
    async createProjectActivity(input) {
        const title = Validator.requiredText(input.titulo, 'o título');
        const date = Validator.date(input.data_limite);
        const status = String(input.status ?? ActivityStatus.PENDING);
        if (!isValidStatus(status)) {
            throw new Error('Status de atividade inválido.');
        }
        const obraId = Validator.id(input.obra_id);
        const description = String(input.descricao ?? '').trim();

        await this.activities.addActivity({ obraId, title, description, date, status });
    }

    async changeStatus(activityId, status) {
        const id = Validator.id(activityId);
        if (!isValidStatus(status)) {
            throw new Error('Dados inválidos para atualização de status.');
        }
        const activity = this.activities.state.activities.find(item => item.id === id);
        if (!activity) {
            throw new Error('Atividade não encontrada.');
        }
        await this.activities.updateActivityStatus(id, status);
    }

    async cycleStatus(activityId) {
        const activity = this.activities.state.activities.find(item => item.id === Number(activityId));
        if (!activity) {
            throw new Error('Atividade não encontrada.');
        }
        await this.changeStatus(activity.id, nextStatus(activity.status));
    }

    async remove(activityId) {
        await this.activities.deleteActivity(activityId);
    }
}
