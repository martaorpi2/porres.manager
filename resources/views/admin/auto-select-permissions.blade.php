<script>
document.addEventListener('DOMContentLoaded', function() {
    var rolePermissionsMap = @json($rolePermissionsMap);

    function selectedRoleIdSet(selectedRoles) {
        var set = new Set();
        selectedRoles.forEach(function(roleId) {
            set.add(String(roleId));
        });
        return set;
    }

    /** True si el permiso está definido en algún rol que NO está seleccionado (copias directas “fantasma”). */
    function permissionGrantedByUnselectedRole(permissionId, selectedRoleSet) {
        permissionId = String(permissionId);
        for (var rid in rolePermissionsMap) {
            if (!Object.prototype.hasOwnProperty.call(rolePermissionsMap, rid)) {
                continue;
            }
            if (selectedRoleSet.has(String(rid))) {
                continue;
            }
            var perms = rolePermissionsMap[rid] || [];
            for (var i = 0; i < perms.length; i++) {
                if (String(perms[i]) === permissionId) {
                    return true;
                }
            }
        }
        return false;
    }

    function updatePermissionsFromRoles() {
        var rolesHiddenInput = document.querySelector('input[name="roles"][type="hidden"]');
        var selectedRoles = [];

        if (rolesHiddenInput) {
            try {
                selectedRoles = JSON.parse(rolesHiddenInput.value || '[]');
            } catch (e) {
                return;
            }
        }

        var permissionsFromSelectedRoles = new Set();
        selectedRoles.forEach(function(roleId) {
            roleId = parseInt(roleId, 10);
            if (rolePermissionsMap[roleId]) {
                rolePermissionsMap[roleId].forEach(function(permissionId) {
                    permissionsFromSelectedRoles.add(String(permissionId));
                });
            }
        });

        var selectedRoleSet = selectedRoleIdSet(selectedRoles);

        var permissionsHiddenInput = document.querySelector('input[name="permissions"][type="hidden"]');
        if (!permissionsHiddenInput) {
            return;
        }

        var current = new Set();
        try {
            var existing = JSON.parse(permissionsHiddenInput.value || '[]');
            existing.forEach(function(permId) {
                current.add(String(permId));
            });
        } catch (e) {
            current = new Set();
        }

        // Quitar del hidden los permisos que solo “venían” de roles ya desmarcados (Spatie los seguiría dando por syncPermissions directo).
        Array.from(current).forEach(function(p) {
            if (permissionsFromSelectedRoles.has(p)) {
                return;
            }
            if (permissionGrantedByUnselectedRole(p, selectedRoleSet)) {
                current.delete(p);
            }
        });

        permissionsFromSelectedRoles.forEach(function(p) {
            current.add(p);
        });

        var finalPermissions = Array.from(current);
        permissionsHiddenInput.value = JSON.stringify(finalPermissions);
        permissionsHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

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

    setTimeout(function() {
        var rolesHiddenInput = document.querySelector('input[name="roles"][type="hidden"]');
        if (!rolesHiddenInput) {
            return;
        }

        rolesHiddenInput.addEventListener('change', function() {
            updatePermissionsFromRoles();
        });

        var rolesContainer = rolesHiddenInput.closest('.form-group');
        if (rolesContainer) {
            var roleCheckboxes = rolesContainer.querySelectorAll('input[type="checkbox"]');
            roleCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('click', function() {
                    setTimeout(updatePermissionsFromRoles, 100);
                });
            });
        }

        updatePermissionsFromRoles();
    }, 1000);
});
</script>
