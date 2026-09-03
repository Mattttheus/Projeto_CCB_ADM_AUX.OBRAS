// Presentation/Pages — equivalente ao cronograma de page/dashboard.php e calendario.php
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { layout, panel, badge, formatDate, escapeHtml } from '../ui.js';

function rows(store, items) {
    return items.length ? items.map(item => {
        const shown = displayStatus(item);
        return `
        <tr>
            <td><strong>${escapeHtml(item.title)}</strong></td>
            <td>${escapeHtml(store.obraName(item.obraId))}</td>
            <td>${formatDate(item.date)}</td>
            <td><button class="table-action" data-toggle-status="${item.id}" title="Avançar status">${badge(shown, ACTIVITY_STATUS_LABELS[shown])}</button></td>
            <td><button class="table-action" data-delete="activity:${item.id}">Excluir</button></td>
        </tr>`;
    }).join('') : '<tr><td colspan="5"><div class="empty-state">Nenhuma atividade encontrada.</div></td></tr>';
}

export function render() {
    return layout('Atividades', 'Cronograma operacional e pendências da equipe.',
        '<button class="button button-primary" data-action="new-activity">+ Nova atividade</button>')
        + panel('Cronograma', `
            <div class="toolbar"><select class="field" id="activity-filter" aria-label="Filtrar atividades">
                <option value="todas">Todos os status</option>
                <option value="pendente">Pendente</option>
                <option value="em_andamento">Em andamento</option>
                <option value="atrasada">Atrasada</option>
                <option value="concluida">Concluída</option>
            </select></div>
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Atividade</th><th>Obra</th><th>Prazo</th><th>Status</th><th></th></tr></thead>
                <tbody id="activities-table"></tbody>
            </table></div>`);
}

export function bind(ctx) {
    const tbody = document.querySelector('#activities-table');
    const paint = filter => {
        const items = filter === 'todas'
            ? ctx.store.state.activities
            : ctx.store.state.activities.filter(item => displayStatus(item) === filter);
        tbody.innerHTML = rows(ctx.store, items);
    };
    paint('todas');
    document.querySelector('#activity-filter').addEventListener('change', event => paint(event.target.value));
}
