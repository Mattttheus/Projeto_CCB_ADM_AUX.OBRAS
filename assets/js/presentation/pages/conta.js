// Presentation/Pages — equivalente a page/login.php e page/perfil.php
import { layout, panel, notify, escapeHtml } from '../ui.js';
import { ROLE_LABELS } from '../../core/Auth.js';
import { BACKEND_MODE } from '../../infrastructure/persistence/Store.js';
import { Validator } from '../../core/Validator.js';

export function renderLogin() {
    const remote = BACKEND_MODE === 'supabase';
    return `
    <div class="login-page"><div class="login-card">
        <span class="brand-mark">AO</span>
        <p class="eyebrow">Auxiliar Obras</p>
        <h1 class="page-title">Acesse seu painel</h1>
        <p class="page-subtitle">${remote
            ? 'Entre com o e-mail e a senha cadastrados pelo administrador.'
            : 'Modo demonstração para GitHub Pages.'}</p>
        <form id="login-form" class="form-grid">
            <div class="form-field full"><label for="login-email">E-mail</label><input class="field" id="login-email" type="email" ${remote ? '' : 'value="admin@auxiliarobras.local"'} required></div>
            <div class="form-field full"><label for="login-password">Senha</label><input class="field" id="login-password" type="password" ${remote ? '' : 'value="demo123"'} required></div>
            <div class="form-actions full"><button class="button button-primary" type="submit">Entrar no painel</button></div>
        </form>
    </div></div>`;
}

export function bindLogin(ctx) {
    document.querySelector('#login-form').addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.target.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const user = await ctx.auth.login(
                document.querySelector('#login-email').value,
                document.querySelector('#login-password').value
            );
            await ctx.store.refresh();
            ctx.navigate('dashboard');
            notify(`Bem-vindo(a), ${user.name}.`);
        } catch (error) {
            notify(error.message);
            button.disabled = false;
        }
    });
}

export function renderPerfil(ctx) {
    const user = ctx.auth.user();
    const remote = BACKEND_MODE === 'supabase';
    const minhasObras = ctx.store.obrasFor(user);
    const obrasDesignadas = !ctx.auth.hasFullProjectAccess()
        ? panel('Obras designadas', `<div class="data-list">${minhasObras.map(obra => `
            <div class="list-row"><div class="row-main"><div class="row-title">${escapeHtml(obra.name)}</div>
            <div class="row-meta">${escapeHtml(obra.city ?? '')}</div></div></div>`).join('')
            || '<div class="empty-state">Nenhuma obra designada pelo administrador até o momento.</div>'}</div>`)
        : '';
    return layout('Meu perfil', 'Dados da sua conta neste espaço de trabalho.')
        + panel('Dados do usuário', `
            <form id="profile-form" class="form-grid">
                <div class="form-field"><label for="profile-name">Nome</label><input class="field" id="profile-name" name="nome" value="${escapeHtml(user?.name ?? '')}" required></div>
                <div class="form-field"><label for="profile-email">E-mail</label><input class="field" id="profile-email" name="email" type="email" value="${escapeHtml(user?.email ?? '')}" required></div>
                <div class="form-field"><label>Perfil de acesso</label><input class="field" value="${ROLE_LABELS[user?.role] ?? ''}" disabled></div>
                <div class="form-field full"><label>Sobre este modo</label>
                    <div class="row-meta">${remote
                ? 'Dados sincronizados com o banco Supabase. O acesso às obras é controlado pelo administrador via responsáveis designados.'
                : 'Esta versão roda inteiramente no navegador. Os dados ficam somente neste dispositivo, sem banco de dados, e-mails ou uploads compartilhados.'}</div></div>
                <div class="form-actions full"><button class="button button-primary" type="submit">Salvar alterações</button></div>
            </form>`)
        + obrasDesignadas;
}

export function bindPerfil(ctx) {
    document.querySelector('#profile-form').addEventListener('submit', async event => {
        event.preventDefault();
        try {
            const user = ctx.auth.user();
            await ctx.store.updateProfile(user.id, {
                name: Validator.requiredText(document.querySelector('#profile-name').value, 'o nome'),
                email: Validator.email(document.querySelector('#profile-email').value),
            });
            ctx.rerender();
            notify('Perfil atualizado.');
        } catch (error) {
            notify(error.message);
        }
    });
}
