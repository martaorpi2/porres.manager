<script>
document.addEventListener('DOMContentLoaded', function() {
    var rolePermissionsMap = @json($rolePermissionsMap);
    
    console.log('Role permissions map loaded:', rolePermissionsMap);
    
    // Función para marcar permisos basados en roles seleccionados
    function updatePermissionsFromRoles() {
        // Buscar el campo hidden de roles que contiene el JSON con los IDs seleccionados
        var rolesHiddenInput = document.querySelector('input[name="roles"][type="hidden"]');
        var selectedRoles = [];
        
        if (rolesHiddenInput) {
            try {
                selectedRoles = JSON.parse(rolesHiddenInput.value || '[]');
                console.log('Selected roles:', selectedRoles);
            } catch (e) {
                console.error('Error parsing roles:', e);
            }
        }
        
        // Colectar todos los permisos de los roles seleccionados
        var permissionsToCheck = new Set();
        selectedRoles.forEach(function(roleId) {
            roleId = parseInt(roleId);
            if (rolePermissionsMap[roleId]) {
                console.log('Role ' + roleId + ' has permissions:', rolePermissionsMap[roleId]);
                rolePermissionsMap[roleId].forEach(function(permissionId) {
                    permissionsToCheck.add(permissionId.toString());
                });
            }
        });
        
        console.log('Permissions to check from roles:', Array.from(permissionsToCheck));
        
        // Buscar el campo hidden de permisos
        var permissionsHiddenInput = document.querySelector('input[name="permissions"][type="hidden"]');
        var currentPermissions = new Set();
        
        if (permissionsHiddenInput) {
            try {
                var existingPermissions = JSON.parse(permissionsHiddenInput.value || '[]');
                existingPermissions.forEach(function(permId) {
                    currentPermissions.add(permId.toString());
                });
                console.log('Current permissions:', Array.from(currentPermissions));
            } catch (e) {
                console.error('Error parsing permissions:', e);
            }
        }
        
        // Combinar permisos de roles y permisos actuales
        permissionsToCheck.forEach(function(permissionId) {
            currentPermissions.add(permissionId);
        });
        
        var finalPermissions = Array.from(currentPermissions);
        console.log('Final permissions to set:', finalPermissions);
        
        // Actualizar el valor del campo hidden de permisos
        if (permissionsHiddenInput) {
            permissionsHiddenInput.value = JSON.stringify(finalPermissions);
            
            // Disparar el evento change para que Backpack actualice los checkboxes
            permissionsHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Actualizar visualmente los checkboxes
            var permissionsContainer = permissionsHiddenInput.closest('.form-group');
            if (permissionsContainer) {
                var checkboxes = permissionsContainer.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(function(checkbox) {
                    var isChecked = finalPermissions.includes(checkbox.value);
                    if (checkbox.checked !== isChecked) {
                        checkbox.checked = isChecked;
                    }
                });
            }
        }
    }
    
    // Esperar a que Backpack inicialice los campos
    setTimeout(function() {
        console.log('Setting up role change listeners...');
        
        // Buscar el campo hidden de roles
        var rolesHiddenInput = document.querySelector('input[name="roles"][type="hidden"]');
        
        if (rolesHiddenInput) {
            // Escuchar cambios en el campo hidden de roles
            rolesHiddenInput.addEventListener('change', function() {
                console.log('Roles changed!');
                updatePermissionsFromRoles();
            });
            
            // También escuchar clics en los checkboxes visuales de roles
            var rolesContainer = rolesHiddenInput.closest('.form-group');
            if (rolesContainer) {
                var roleCheckboxes = rolesContainer.querySelectorAll('input[type="checkbox"]');
                roleCheckboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('click', function() {
                        setTimeout(updatePermissionsFromRoles, 100);
                    });
                });
            }
            
            // Ejecutar al cargar si hay roles ya seleccionados
            updatePermissionsFromRoles();
        }
    }, 1000);
});
</script>

