// Presentation/Pages — equivalente a page/gerenciar_obra.php
import { layout, panel, badge, money, escapeHtml } from '../ui.js';

function rows(store, items) {
    return items.length ? items.map(item => `
        <tr>
            <td><strong>${escapeHtml(item.name)}</strong><div class="row-meta">${escapeHtml(item.city)}</div></td>
            <td>${badge(item.status === 'Em andamento' ? 'em_andamento' : 'pendente', item.status)}</td>
            <td><div class="progress-track"><div class="progress-fill" style="width:${item.progress}%"></div></div><small>${item.progress}%</small></td>
            <td>${money(item.budget)}</td>
            <td><button class="table-action" data-delete="obra:${item.id}">Excluir</button></td>
        </tr>`).join('')
        : '<tr><td colspan="5"><div class="empty-state">Nenhuma obra encontrada.</div></td></tr>';
}

export function render() {
    return layout('Obras', 'Portfólio de projetos e acompanhamento de execução.',
        '<button class="button button-primary" data-action="new-obra">+ Nova obra</button>')
        + panel('Todos os projetos', `
            <div class="toolbar"><input class="field" id="obra-filter" placeholder="Buscar por nome ou cidade" aria-label="Buscar obras"></div>
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Projeto</th><th>Status</th><th>Progresso</th><th>Orçamento</th><th></th></tr></thead>
                <tbody id="obras-table"></tbody>
            </table></div>`);
}

export function bind(ctx) {
    const tbody = document.querySelector('#obras-table');
    const filter = document.querySelector('#obra-filter');
    const paint = term => {
        const query = term.toLowerCase();
        tbody.innerHTML = rows(ctx.store, ctx.store.state.obras
            .filter(item => `${item.name} ${item.city}`.toLowerCase().includes(query)));
    };
    paint('');
    filter.addEventListener('input', event => paint(event.target.value));
}
