// Application/Activity — espelha app/Application/Activity/ActivityService.php
import { store } from '../../infrastructure/persistence/LocalStore.js';
import { Validator } from '../../core/Validator.js';
import { ActivityStatus, isValidStatus, nextStatus } from '../../domain/activity/ActivityStatus.js';

export class ActivityService {
    constructor(repository = store) {
        this.activities = repository;
    }

    /** Regra equivalente a createProjectActivity() do PHP. */
    createProjectActivity(input) {
        const title = Validator.requiredText(input.titulo, 'o título');
        const date = Validator.date(input.data_limite);
        const status = String(input.status ?? ActivityStatus.PENDING);
        if (!isValidStatus(status)) {
            throw new Error('Status de atividade inválido.');
        }
        const obraId = Validator.id(input.obra_id);
        const description = String(input.descricao ?? '').trim();

        this.activities.state.activities.push({
            id: this.activities.nextId(this.activities.state.activities),
            obraId,
            title,
            description,
            date,
            status,
        });
        this.activities.save();
        this.activities.log(`Atividade "${title}" criada.`);
    }

    changeStatus(activityId, status) {
        const id = Validator.id(activityId);
        if (!isValidStatus(status)) {
            throw new Error('Dados inválidos para atualização de status.');
        }
        const activity = this.activities.state.activities.find(item => item.id === id);
        if (!activity) {
            throw new Error('Atividade não encontrada.');
        }
        activity.status = status;
        this.activities.save();
    }

    cycleStatus(activityId) {
        const activity = this.activities.state.activities.find(item => item.id === Number(activityId));
        if (!activity) {
            throw new Error('Atividade não encontrada.');
        }
        this.changeStatus(activity.id, nextStatus(activity.status));
    }

    remove(activityId) {
        this.activities.state.activities = this.activities.state.activities
            .filter(item => item.id !== Number(activityId));
        this.activities.save();
    }
}
