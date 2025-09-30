// Globální proměnné
let currentUser = null;
let vazbyData = [];
let usersData = [];

// DOM elementy
const elements = {
    // Sections
    loginSection: document.getElementById('loginSection'),
    vazbySection: document.getElementById('vazbySection'),
    usersSection: document.getElementById('usersSection'),
    profileSection: document.getElementById('profileSection'),
    
    // Navigation
    mainNav: document.getElementById('mainNav'),
    usersBtn: document.getElementById('usersBtn'),
    profileBtn: document.getElementById('profileBtn'),
    logoutBtn: document.getElementById('logoutBtn'),
    
    // Forms
    loginForm: document.getElementById('loginForm'),
    vazbaForm: document.getElementById('vazbaForm'),
    userForm: document.getElementById('userForm'),
    passwordForm: document.getElementById('passwordForm'),
    editVazbaForm: document.getElementById('editVazbaForm'),
    editUserForm: document.getElementById('editUserForm'),
    
    // Lists
    vazbyList: document.getElementById('vazbyList'),
    usersList: document.getElementById('usersList'),
    
    // Buttons
    addVazbaBtn: document.getElementById('addVazbaBtn'),
    addUserBtn: document.getElementById('addUserBtn'),
    cancelVazbaBtn: document.getElementById('cancelVazbaBtn'),
    cancelUserBtn: document.getElementById('cancelUserBtn'),
    
    // Forms containers
    addVazbaForm: document.getElementById('addVazbaForm'),
    addUserForm: document.getElementById('addUserForm'),
    
    // Modals
    editVazbaModal: document.getElementById('editVazbaModal'),
    editUserModal: document.getElementById('editUserModal'),
    
    // Profile
    profileUsername: document.getElementById('profileUsername'),
    profileEmail: document.getElementById('profileEmail'),
    profileRole: document.getElementById('profileRole'),
    
    // Utils
    loading: document.getElementById('loading'),
    toast: document.getElementById('toast'),
    loginError: document.getElementById('loginError')
};

// API funkce
class API {
    static baseUrl = 'api';
    
    static async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    // Auth endpoints
    static async login(username, password) {
        return this.request('auth/login', {
            method: 'POST',
            body: { username, password }
        });
    }
    
    static async logout() {
        return this.request('auth/logout');
    }
    
    static async getAuthStatus() {
        return this.request('auth/status');
    }
    
    static async changePassword(currentPassword, newPassword) {
        return this.request('auth/change-password', {
            method: 'POST',
            body: {
                current_password: currentPassword,
                new_password: newPassword
            }
        });
    }
    
    // Vazby endpoints
    static async getVazby() {
        return this.request('vazby');
    }
    
    static async createVazba(vazbaData) {
        return this.request('vazby', {
            method: 'POST',
            body: vazbaData
        });
    }
    
    static async updateVazba(id, vazbaData) {
        return this.request(`vazby/${id}`, {
            method: 'PUT',
            body: vazbaData
        });
    }
    
    static async deleteVazba(id) {
        return this.request(`vazby/${id}`, {
            method: 'DELETE'
        });
    }
    
    // Users endpoints
    static async getUsers() {
        return this.request('users');
    }
    
    static async createUser(userData) {
        return this.request('users', {
            method: 'POST',
            body: userData
        });
    }
    
    static async updateUser(userData) {
        return this.request('users', {
            method: 'PUT',
            body: userData
        });
    }
    
    static async deleteUser(id) {
        return this.request(`users/${id}`, {
            method: 'DELETE'
        });
    }
}

// Utility funkce
class Utils {
    static showLoading() {
        elements.loading.classList.add('active');
    }
    
    static hideLoading() {
        elements.loading.classList.remove('active');
    }
    
    static showToast(message, type = 'info') {
        elements.toast.textContent = message;
        elements.toast.className = `toast ${type}`;
        elements.toast.classList.add('show');
        
        setTimeout(() => {
            elements.toast.classList.remove('show');
        }, 4000);
    }
    
    static showError(message, container = null) {
        if (container) {
            container.textContent = message;
            container.classList.add('show');
            setTimeout(() => container.classList.remove('show'), 5000);
        } else {
            this.showToast(message, 'error');
        }
    }
    
    static hideError(container) {
        if (container) {
            container.classList.remove('show');
        }
    }
    
    static formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('cs-CZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    static getRoleText(role) {
        const roles = {
            'admin': 'Administrator',
            'editor': 'Editor',
            'viewer': 'Prohlížeč'
        };
        return roles[role] || role;
    }
    
    static validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    static validateUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }
}

// Správa sekcí
class SectionManager {
    static showSection(sectionName) {
        // Skryt všechny sekce
        document.querySelectorAll('.section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Odstranit aktivní třídu z nav tlačítek
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Zobrazit požadovanou sekci
        const section = document.getElementById(`${sectionName}Section`);
        if (section) {
            section.classList.add('active');
        }
        
        // Přidat aktivní třídu k nav tlačítku
        const navBtn = document.querySelector(`[data-section="${sectionName}"]`);
        if (navBtn) {
            navBtn.classList.add('active');
        }
        
        // Načíst data pro sekci
        this.loadSectionData(sectionName);
    }
    
    static async loadSectionData(sectionName) {
        try {
            Utils.showLoading();
            
            switch (sectionName) {
                case 'vazby':
                    await VazbyManager.loadVazby();
                    break;
                case 'users':
                    if (currentUser && currentUser.role === 'admin') {
                        await UsersManager.loadUsers();
                    }
                    break;
                case 'profile':
                    ProfileManager.loadProfile();
                    break;
            }
        } catch (error) {
            Utils.showError('Chyba při načítání dat: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
}

// Správa autentizace
class AuthManager {
    static async checkAuth() {
        try {
            const response = await API.getAuthStatus();
            
            if (response.authenticated) {
                currentUser = response.user;
                this.showAuthenticatedUI();
                SectionManager.showSection('vazby');
            } else {
                this.showLoginUI();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            this.showLoginUI();
        }
    }
    
    static async login(username, password) {
        try {
            Utils.showLoading();
            Utils.hideError(elements.loginError);
            
            const response = await API.login(username, password);
            
            if (response.success && response.user) {
                currentUser = response.user;
                this.showAuthenticatedUI();
                SectionManager.showSection('vazby');
                Utils.showToast('Přihlášení úspěšné', 'success');
            }
        } catch (error) {
            Utils.showError(error.message, elements.loginError);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async logout() {
        try {
            await API.logout();
            currentUser = null;
            this.showLoginUI();
            Utils.showToast('Odhlášení úspěšné', 'success');
        } catch (error) {
            Utils.showError('Chyba při odhlášení: ' + error.message);
        }
    }
    
    static showLoginUI() {
        elements.loginSection.classList.add('active');
        elements.vazbySection.classList.remove('active');
        elements.usersSection.classList.remove('active');
        elements.profileSection.classList.remove('active');
        
        elements.mainNav.style.display = 'none';
        elements.usersBtn.style.display = 'none';
        elements.profileBtn.style.display = 'none';
        elements.logoutBtn.style.display = 'none';
    }
    
    static showAuthenticatedUI() {
        elements.loginSection.classList.remove('active');
        elements.mainNav.style.display = 'flex';
        elements.profileBtn.style.display = 'block';
        elements.logoutBtn.style.display = 'block';
        
        // Zobrazit správu uživatelů jen pro adminy
        if (currentUser && currentUser.role === 'admin') {
            elements.usersBtn.style.display = 'block';
        } else {
            elements.usersBtn.style.display = 'none';
        }
        
        // Zobrazit tlačítko pro přidání vazby pro editory a adminy
        if (currentUser && ['editor', 'admin'].includes(currentUser.role)) {
            elements.addVazbaBtn.style.display = 'block';
        } else {
            elements.addVazbaBtn.style.display = 'none';
        }
    }
}

// Správa vazeb
class VazbyManager {
    static async loadVazby() {
        try {
            const response = await API.getVazby();
            vazbyData = response.vazby || [];
            this.renderVazby();
        } catch (error) {
            Utils.showError('Chyba při načítání vazeb: ' + error.message);
        }
    }
    
    static renderVazby() {
        if (!elements.vazbyList) return;
        
        if (vazbyData.length === 0) {
            elements.vazbyList.innerHTML = `
                <div class="card text-center">
                    <h3>Není k dispozici žádná vazba</h3>
                    <p>Zatím nebyly přidány žádné vazby.</p>
                </div>
            `;
            return;
        }
        
        elements.vazbyList.innerHTML = vazbyData.map(vazba => this.createVazbaCard(vazba)).join('');
    }
    
    static createVazbaCard(vazba) {
        const canEdit = currentUser && ['editor', 'admin'].includes(currentUser.role);
        const canApprove = currentUser && currentUser.role === 'admin';
        const canDelete = currentUser && currentUser.role === 'admin';
        
        return `
            <div class="vazba-card" data-id="${vazba.id}">
                <div class="vazba-header">
                    <div>
                        <h3 class="vazba-title">${vazba.nazev}</h3>
                        <a href="${vazba.url}" target="_blank" class="vazba-url">${vazba.url}</a>
                    </div>
                </div>
                
                <div class="vazba-meta">
                    ${vazba.kategorie ? `<span class="vazba-kategorie">${vazba.kategorie}</span>` : ''}
                    <span class="vazba-status ${vazba.schvaleno ? 'schvaleno' : 'neschvaleno'}">
                        ${vazba.schvaleno ? 'Schváleno' : 'Neschváleno'}
                    </span>
                    <span>Vytvořil: ${vazba.created_by_username || 'Neznámý'}</span>
                </div>
                
                ${vazba.popis ? `<p class="vazba-popis">${vazba.popis}</p>` : ''}
                
                <div class="vazba-actions">
                    ${canEdit ? `<button class="btn btn-primary btn-edit" onclick="VazbyManager.editVazba(${vazba.id})">Upravit</button>` : ''}
                    ${canApprove && !vazba.schvaleno ? `<button class="btn btn-success btn-approve" onclick="VazbyManager.approveVazba(${vazba.id})">Schválit</button>` : ''}
                    ${canDelete ? `<button class="btn btn-danger btn-delete" onclick="VazbyManager.deleteVazba(${vazba.id}, '${vazba.nazev}')">Smazat</button>` : ''}
                </div>
            </div>
        `;
    }
    
    static async createVazba(formData) {
        try {
            Utils.showLoading();
            
            const response = await API.createVazba(formData);
            
            if (response.success) {
                await this.loadVazby();
                this.hideAddForm();
                elements.vazbaForm.reset();
                Utils.showToast('Vazba byla úspěšně přidána', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při vytváření vazby: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async updateVazba(id, formData) {
        try {
            Utils.showLoading();
            
            const response = await API.updateVazba(id, formData);
            
            if (response.success) {
                await this.loadVazby();
                this.hideEditModal();
                Utils.showToast('Vazba byla úspěšně aktualizována', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při aktualizaci vazby: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async approveVazba(id) {
        try {
            Utils.showLoading();
            
            const response = await API.updateVazba(id, { schvaleno: true });
            
            if (response.success) {
                await this.loadVazby();
                Utils.showToast('Vazba byla schválena', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při schvalování vazby: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async deleteVazba(id, nazev) {
        if (!confirm(`Opravdu chcete smazat vazbu "${nazev}"?`)) {
            return;
        }
        
        try {
            Utils.showLoading();
            
            const response = await API.deleteVazba(id);
            
            if (response.success) {
                await this.loadVazby();
                Utils.showToast('Vazba byla smazána', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při mazání vazby: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static editVazba(id) {
        const vazba = vazbyData.find(v => v.id == id);
        if (!vazba) return;
        
        document.getElementById('edit_vazba_id').value = vazba.id;
        document.getElementById('edit_vazba_nazev').value = vazba.nazev;
        document.getElementById('edit_vazba_url').value = vazba.url;
        document.getElementById('edit_vazba_kategorie').value = vazba.kategorie || '';
        document.getElementById('edit_vazba_popis').value = vazba.popis || '';
        
        this.showEditModal();
    }
    
    static showAddForm() {
        elements.addVazbaForm.style.display = 'block';
        elements.addVazbaBtn.style.display = 'none';
    }
    
    static hideAddForm() {
        elements.addVazbaForm.style.display = 'none';
        elements.addVazbaBtn.style.display = 'block';
        elements.vazbaForm.reset();
    }
    
    static showEditModal() {
        elements.editVazbaModal.classList.add('active');
    }
    
    static hideEditModal() {
        elements.editVazbaModal.classList.remove('active');
        elements.editVazbaForm.reset();
    }
}

// Správa uživatelů
class UsersManager {
    static async loadUsers() {
        try {
            const response = await API.getUsers();
            usersData = response.users || [];
            this.renderUsers();
        } catch (error) {
            Utils.showError('Chyba při načítání uživatelů: ' + error.message);
        }
    }
    
    static renderUsers() {
        if (!elements.usersList) return;
        
        if (usersData.length === 0) {
            elements.usersList.innerHTML = `
                <div class="card text-center">
                    <h3>Není k dispozici žádný uživatel</h3>
                </div>
            `;
            return;
        }
        
        let html = `
            <div class="users-header">
                <div>Uživatelské jméno</div>
                <div>Email</div>
                <div>Role</div>
                <div>Stav</div>
                <div>Akce</div>
            </div>
        `;
        
        html += usersData.map(user => this.createUserRow(user)).join('');
        
        elements.usersList.innerHTML = html;
    }
    
    static createUserRow(user) {
        const isCurrentUser = currentUser && currentUser.id === user.id;
        
        return `
            <div class="user-row">
                <div data-label="Jméno:">${user.username}</div>
                <div data-label="Email:">${user.email}</div>
                <div data-label="Role:"><span class="user-role role-${user.role}">${Utils.getRoleText(user.role)}</span></div>
                <div data-label="Stav:"><span class="user-status status-${user.active ? 'active' : 'inactive'}">${user.active ? 'Aktivní' : 'Neaktivní'}</span></div>
                <div data-label="Akce:" class="user-actions">
                    <button class="btn btn-primary btn-edit" onclick="UsersManager.editUser(${user.id})">Upravit</button>
                    ${!isCurrentUser ? `<button class="btn btn-danger btn-delete" onclick="UsersManager.deleteUser(${user.id}, '${user.username}')">Smazat</button>` : ''}
                </div>
            </div>
        `;
    }
    
    static async createUser(formData) {
        try {
            Utils.showLoading();
            
            // Validace
            if (!Utils.validateEmail(formData.email)) {
                throw new Error('Neplatný formát emailu');
            }
            
            const response = await API.createUser(formData);
            
            if (response.success) {
                await this.loadUsers();
                this.hideAddForm();
                elements.userForm.reset();
                Utils.showToast('Uživatel byl úspěšně přidán', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při vytváření uživatele: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async updateUser(formData) {
        try {
            Utils.showLoading();
            
            // Validace
            if (!Utils.validateEmail(formData.email)) {
                throw new Error('Neplatný formát emailu');
            }
            
            const response = await API.updateUser(formData);
            
            if (response.success) {
                await this.loadUsers();
                this.hideEditModal();
                Utils.showToast('Uživatel byl úspěšně aktualizován', 'success');
                
                // Pokud uživatel upravil sebe, aktualizovat currentUser
                if (currentUser && currentUser.id == formData.id) {
                    const updatedUser = response.user;
                    currentUser = { ...currentUser, ...updatedUser };
                    ProfileManager.loadProfile();
                }
            }
        } catch (error) {
            Utils.showError('Chyba při aktualizaci uživatele: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static async deleteUser(id, username) {
        if (!confirm(`Opravdu chcete smazat uživatele "${username}"?`)) {
            return;
        }
        
        try {
            Utils.showLoading();
            
            const response = await API.deleteUser(id);
            
            if (response.success) {
                await this.loadUsers();
                Utils.showToast('Uživatel byl smazán', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při mazání uživatele: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
    
    static editUser(id) {
        const user = usersData.find(u => u.id == id);
        if (!user) return;
        
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_user_username').value = user.username;
        document.getElementById('edit_user_email').value = user.email;
        document.getElementById('edit_user_role').value = user.role;
        document.getElementById('edit_user_active').value = user.active ? '1' : '0';
        document.getElementById('edit_user_password').value = '';
        
        this.showEditModal();
    }
    
    static showAddForm() {
        elements.addUserForm.style.display = 'block';
        elements.addUserBtn.style.display = 'none';
    }
    
    static hideAddForm() {
        elements.addUserForm.style.display = 'none';
        elements.addUserBtn.style.display = 'block';
        elements.userForm.reset();
    }
    
    static showEditModal() {
        elements.editUserModal.classList.add('active');
    }
    
    static hideEditModal() {
        elements.editUserModal.classList.remove('active');
        elements.editUserForm.reset();
    }
}

// Správa profilu
class ProfileManager {
    static loadProfile() {
        if (!currentUser) return;
        
        elements.profileUsername.textContent = currentUser.username;
        elements.profileEmail.textContent = currentUser.email;
        elements.profileRole.textContent = Utils.getRoleText(currentUser.role);
    }
    
    static async changePassword(currentPassword, newPassword, confirmPassword) {
        try {
            if (newPassword !== confirmPassword) {
                throw new Error('Nová hesla se neshodují');
            }
            
            if (newPassword.length < 4) {
                throw new Error('Nové heslo musí mít alespoň 4 znaky');
            }
            
            Utils.showLoading();
            
            const response = await API.changePassword(currentPassword, newPassword);
            
            if (response.success) {
                elements.passwordForm.reset();
                Utils.showToast('Heslo bylo úspěšně změněno', 'success');
            }
        } catch (error) {
            Utils.showError('Chyba při změně hesla: ' + error.message);
        } finally {
            Utils.hideLoading();
        }
    }
}

// Event listeners
function setupEventListeners() {
    // Přihlášení
    elements.loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        await AuthManager.login(formData.get('username'), formData.get('password'));
    });
    
    // Odhlášení
    elements.logoutBtn.addEventListener('click', async () => {
        await AuthManager.logout();
    });
    
    // Navigace
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const section = btn.getAttribute('data-section');
            SectionManager.showSection(section);
        });
    });
    
    // Vazby formulář
    elements.vazbaForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            nazev: formData.get('nazev'),
            url: formData.get('url'),
            popis: formData.get('popis'),
            kategorie: formData.get('kategorie')
        };
        
        // Validace
        if (!Utils.validateUrl(data.url)) {
            Utils.showError('Neplatný formát URL');
            return;
        }
        
        await VazbyManager.createVazba(data);
    });
    
    // Edit vazby formulář
    elements.editVazbaForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const id = formData.get('id') || document.getElementById('edit_vazba_id').value;
        const data = {
            nazev: formData.get('nazev'),
            url: formData.get('url'),
            popis: formData.get('popis'),
            kategorie: formData.get('kategorie')
        };
        
        // Validace
        if (!Utils.validateUrl(data.url)) {
            Utils.showError('Neplatný formát URL');
            return;
        }
        
        await VazbyManager.updateVazba(id, data);
    });
    
    // Uživatelé formulář
    elements.userForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password'),
            role: formData.get('role')
        };
        
        await UsersManager.createUser(data);
    });
    
    // Edit uživatelé formulář
    elements.editUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            id: document.getElementById('edit_user_id').value,
            username: formData.get('username'),
            email: formData.get('email'),
            role: formData.get('role'),
            active: formData.get('active') === '1'
        };
        
        const password = formData.get('password');
        if (password) {
            data.password = password;
        }
        
        await UsersManager.updateUser(data);
    });
    
    // Heslo formulář
    elements.passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        await ProfileManager.changePassword(
            formData.get('current_password'),
            formData.get('new_password'),
            formData.get('confirm_password')
        );
    });
    
    // Tlačítka
    elements.addVazbaBtn.addEventListener('click', () => VazbyManager.showAddForm());
    elements.cancelVazbaBtn.addEventListener('click', () => VazbyManager.hideAddForm());
    
    elements.addUserBtn.addEventListener('click', () => UsersManager.showAddForm());
    elements.cancelUserBtn.addEventListener('click', () => UsersManager.hideAddForm());
    
    // Modal close
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // Modal background close
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
}

// Inicializace aplikace
document.addEventListener('DOMContentLoaded', async () => {
    setupEventListeners();
    await AuthManager.checkAuth();
});

// Export pro globální použití
window.VazbyManager = VazbyManager;
window.UsersManager = UsersManager;
window.Utils = Utils;