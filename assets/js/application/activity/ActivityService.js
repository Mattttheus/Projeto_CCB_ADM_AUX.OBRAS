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

        await this.activities.addActivity({ obraId, title, description, date, status, type: 'unico' });
    }

    /** Manutenção recorrente da obra: sem prazo fixo, repete num dia da semana (0=domingo .. 6=sábado). */
    async createMaintenanceActivity(input) {
        const title = Validator.requiredText(input.titulo, 'o título');
        const obraId = Validator.id(input.obra_id);
        const description = String(input.descricao ?? '').trim();
        const dayOfWeek = Number(input.dia_semana);
        if (!Number.isInteger(dayOfWeek) || dayOfWeek < 0 || dayOfWeek > 6) {
            throw new Error('Selecione um dia da semana válido.');
        }

        await this.activities.addActivity({
            obraId, title, description, date: null, status: ActivityStatus.PENDING,
            type: 'recorrente', dayOfWeek,
        });
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
