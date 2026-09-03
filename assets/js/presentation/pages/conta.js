// Presentation/Pages — equivalente a page/login.php e page/perfil.php
import { layout, panel, notify } from '../ui.js';
import { ROLE_LABELS } from '../../core/Auth.js';
import { Validator } from '../../core/Validator.js';

export function renderLogin() {
    return `
    <div class="login-page"><div class="login-card">
        <span class="brand-mark">AO</span>
        <p class="eyebrow">Auxiliar Obras</p>
        <h1 class="page-title">Acesse seu painel</h1>
        <p class="page-subtitle">Modo demonstração para GitHub Pages.</p>
        <form id="login-form" class="form-grid">
            <div class="form-field full"><label for="login-email">E-mail</label><input class="field" id="login-email" type="email" value="admin@auxiliarobras.local" required></div>
            <div class="form-field full"><label for="login-password">Senha</label><input class="field" id="login-password" type="password" value="demo123" required></div>
            <div class="form-actions full"><button class="button button-primary" type="submit">Entrar no painel</button></div>
        </form>
        <div class="row-meta">Perfis de teste: admin@auxiliarobras.local / demo123 · suporte@auxiliarobras.local / suporte123 · obras@auxiliarobras.local / obras123</div>
        <div class="row-meta">A autenticação real (sessão segura) permanece disponível na versão PHP.</div>
    </div></div>`;
}

export function bindLogin(ctx) {
    document.querySelector('#login-form').addEventListener('submit', event => {
        event.preventDefault();
        try {
            const user = ctx.auth.login(
                document.querySelector('#login-email').value,
                document.querySelector('#login-password').value
            );
            ctx.navigate('dashboard');
            notify(`Bem-vindo(a), ${user.name}.`);
        } catch (error) {
            notify(error.message);
        }
    });
}

export function renderPerfil(ctx) {
    const user = ctx.auth.user();
    return layout('Meu perfil', 'Dados da sua conta neste espaço de trabalho.')
        + panel('Dados do usuário', `
            <form id="profile-form" class="form-grid">
                <div class="form-field"><label for="profile-name">Nome</label><input class="field" id="profile-name" name="nome" value="${user?.name ?? ''}" required></div>
                <div class="form-field"><label for="profile-email">E-mail</label><input class="field" id="profile-email" name="email" type="email" value="${user?.email ?? ''}" required></div>
                <div class="form-field"><label>Perfil de acesso</label><input class="field" value="${ROLE_LABELS[user?.role] ?? ''}" disabled></div>
                <div class="form-field full"><label>Sobre este modo</label>
                    <div class="row-meta">Esta versão roda inteiramente no navegador. Os dados ficam somente neste dispositivo, sem banco de dados, e-mails ou uploads compartilhados.</div></div>
                <div class="form-actions full"><button class="button button-primary" type="submit">Salvar alterações</button></div>
            </form>`);
}

export function bindPerfil(ctx) {
    document.querySelector('#profile-form').addEventListener('submit', event => {
        event.preventDefault();
        try {
            const user = ctx.auth.user();
            user.name = Validator.requiredText(document.querySelector('#profile-name').value, 'o nome');
            user.email = Validator.email(document.querySelector('#profile-email').value);
            ctx.store.save();
            ctx.rerender();
            notify('Perfil atualizado neste dispositivo.');
        } catch (error) {
            notify(error.message);
        }
    });
}
