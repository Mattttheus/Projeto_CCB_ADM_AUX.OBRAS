// Presentation/Pages — equivalente a page/obras_calendario.php e page/calendario.php.
// Calendário interativo (FullCalendar) com abas por obra, cores por status e
// destaque para atividades em atraso. O responsável só vê as abas das obras
// designadas a ele (filtro via store.obrasFor + RLS no Supabase).
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { layout, panel, badge, formatDate, escapeHtml, openInfoModal } from '../ui.js';

const STATUS_COLORS = {
    pendente: '#64748b',
    em_andamento: '#d97706',
    concluida: '#059669',
    atrasada: '#dc2626',
};

let currentTab = 'all';
let calendar = null;

function activitiesFor(ctx) {
    const obraIds = new Set(ctx.store.obrasFor(ctx.auth.user()).map(obra => obra.id));
    return ctx.store.state.activities.filter(item =>
        obraIds.has(item.obraId) && (currentTab === 'all' || item.obraId === Number(currentTab)));
}

export function render(ctx) {
    const obras = ctx.store.obrasFor(ctx.auth.user());
    // A aba é um estado de módulo: se apontar para uma obra que saiu do escopo
    // (troca de usuário/permissão), volta para "Todas as obras".
    if (currentTab !== 'all' && !obras.some(obra => String(obra.id) === currentTab)) {
        currentTab = 'all';
    }
    const activities = activitiesFor(ctx);
    const overdue = activities.filter(item => displayStatus(item) === 'atrasada').length;

    return layout('Calendário', 'Agenda interativa das obras — clique em uma data para agendar e em um evento para detalhes.')
        + panel('Agenda de atividades', `
            <div class="tab-bar" id="calendar-tabs" role="tablist">
                <button class="tab ${currentTab === 'all' ? 'active' : ''}" data-tab="all" role="tab">Todas as obras</button>
                ${obras.map(obra => `<button class="tab ${String(obra.id) === currentTab ? 'active' : ''}" data-tab="${obra.id}" role="tab">${escapeHtml(obra.name)}</button>`).join('')}
            </div>
            <div class="calendar-meta">
                <span class="legend-item"><i style="background:${STATUS_COLORS.pendente}"></i>Pendente</span>
                <span class="legend-item"><i style="background:${STATUS_COLORS.em_andamento}"></i>Em andamento</span>
                <span class="legend-item"><i style="background:${STATUS_COLORS.concluida}"></i>Concluída</span>
                <span class="legend-item"><i style="background:${STATUS_COLORS.atrasada}"></i>Atrasada</span>
                <span class="legend-spacer"></span>
                ${overdue ? badge('atrasada', `${overdue} em atraso`) : badge('concluida', 'Nada em atraso')}
            </div>
            <div id="calendar"></div>`);
}

export function bind(ctx) {
    document.querySelector('#calendar-tabs')?.addEventListener('click', event => {
        const tab = event.target.closest('[data-tab]')?.dataset.tab;
        if (tab && tab !== currentTab) {
            currentTab = tab;
            ctx.rerender();
        }
    });
}

/** Monta o FullCalendar após o HTML entrar no DOM (hook chamado pelo roteador). */
export function mount(ctx) {
    const el = document.querySelector('#calendar');
    if (!el) return;
    if (typeof FullCalendar === 'undefined') {
        el.innerHTML = '<div class="empty-state">A biblioteca de calendário não carregou (verifique a conexão com o CDN).</div>';
        return;
    }
    if (calendar) {
        calendar.destroy();
        calendar = null;
    }

    const events = activitiesFor(ctx).map(item => {
        const shown = displayStatus(item);
        return {
            id: String(item.id),
            title: `${item.title} · ${ctx.store.obraName(item.obraId)}`,
            start: item.date,
            allDay: true,
            backgroundColor: STATUS_COLORS[shown],
            borderColor: STATUS_COLORS[shown],
            classNames: shown === 'atrasada' ? ['fc-overdue'] : [],
        };
    });

    calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista' },
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
        height: 650,
        events,
        dateClick: info => ctx.openActivityModal(info.dateStr),
        eventClick: info => {
            const item = ctx.store.state.activities.find(entry => entry.id === Number(info.event.id));
            if (!item) return;
            const shown = displayStatus(item);
            openInfoModal(item.title, `
                <div class="data-list">
                    <div class="list-row"><strong>Obra</strong><span>${escapeHtml(ctx.store.obraName(item.obraId))}</span></div>
                    <div class="list-row"><strong>Prazo</strong><span>${formatDate(item.date)}</span></div>
                    <div class="list-row"><strong>Status</strong>${badge(shown, ACTIVITY_STATUS_LABELS[shown])}</div>
                    ${item.description ? `<div class="list-row"><strong>Descrição</strong><span>${escapeHtml(item.description)}</span></div>` : ''}
                </div>
                <div class="form-actions">
                    <button class="button button-primary" data-toggle-status="${item.id}">Avançar status</button>
                    <button class="button button-light" data-delete="activity:${item.id}">Excluir</button>
                </div>`);
        },
    });
    calendar.render();
}
