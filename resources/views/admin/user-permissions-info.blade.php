<div class="permissions-info" style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; border-radius: 4px;">
    <h5 style="margin: 0 0 10px 0; color: #495057;">
        <i class="la la-info-circle"></i> Permisos que se activan con los roles seleccionados:
    </h5>
    <div id="role-permissions-list" style="max-height: 300px; overflow-y: auto; padding: 10px; background-color: white; border-radius: 4px;">
        <p style="margin: 0; color: #6c757d; font-style: italic;">Seleccione roles para ver sus permisos...</p>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Obtener todos los roles con sus permisos desde el parámetro pasado
    const allRoles = @json($roles ?? []);
    
    // Crear un mapa de permisos por rol
    const permissionsByRole = {};
    allRoles.forEach(role => {
        if (role.permissions && role.permissions.length > 0) {
            permissionsByRole[role.id] = role.permissions.map(p => p.name);
        }
    });
    
    function updatePermissionsInfo() {
        const permissionsList = document.getElementById('role-permissions-list');
        if (!permissionsList) return;
        
        // Backpack renderiza checkboxes sin name, necesitamos buscar por contexto
        // Los primeros 10 checkboxes (valores 1-10) son los roles
        const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
        let roleCheckboxes = Array.from(allCheckboxes).slice(0, 10); // Primeros 10 son roles
        
        const selectedRoles = [];
        roleCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedRoles.push(parseInt(checkbox.value));
            }
        });
        
        if (selectedRoles.length === 0) {
            permissionsList.innerHTML = '<p style="margin: 0; color: #6c757d; font-style: italic;">Seleccione roles para ver sus permisos...</p>';
            return;
        }
        
        // Obtener todos los permisos únicos de los roles seleccionados
        const allPermissions = new Set();
        selectedRoles.forEach(roleId => {
            if (permissionsByRole[roleId]) {
                permissionsByRole[roleId].forEach(perm => allPermissions.add(perm));
            }
        });
        
        if (allPermissions.size === 0) {
            permissionsList.innerHTML = '<p style="margin: 0; color: #ffc107;"><i class="la la-exclamation-circle"></i> Los roles seleccionados no tienen permisos asignados.</p>';
            return;
        }
        
        let html = '<ul style="margin: 0; padding-left: 20px; list-style: none;">';
        Array.from(allPermissions).sort().forEach(perm => {
            html += `<li style="padding: 5px 0; color: #495057;">
                <i class="la la-check-circle" style="color: #28a745; margin-right: 5px;"></i>
                ${perm}
            </li>`;
        });
        html += '</ul>';
        
        html += `<p style="margin: 10px 0 0 0; padding-top: 10px; border-top: 1px solid #dee2e6; color: #495057; font-size: 0.9em;">
            <strong>Total:</strong> ${allPermissions.size} permiso(s) único(s)
        </p>`;
        
        permissionsList.innerHTML = html;
    }
    
    // Capturar cambios en checkboxes de roles
    document.addEventListener('click', function(e) {
        if (e.target.type === 'checkbox') {
            const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            const checkboxIndex = Array.from(allCheckboxes).indexOf(e.target);
            // Solo procesar si es uno de los primeros 10 (roles)
            if (checkboxIndex < 10) {
                setTimeout(updatePermissionsInfo, 50);
            }
        }
    });
    
    // Inicializar al cargar
    setTimeout(updatePermissionsInfo, 1000);
})();
</script>

