// =============================================
// ADMIN.JS - Panel de Administración RENTNONO
// Versión FINAL y funcional
// =============================================

// ====== VARIABLES GLOBALES ======
let csrfToken = '';
let esSuperadmin = false;

// ====== SISTEMA DE NOTIFICACIONES ======
window.mostrarNotificacion = function(mensaje, tipo = 'success', duracion = 4000) {
    const notificacion = document.createElement('div');
    notificacion.className = `mensaje-confirmacion ${tipo}`;
    
    let icono = 'fa-check-circle';
    if (tipo === 'error') icono = 'fa-exclamation-circle';
    else if (tipo === 'warning') icono = 'fa-exclamation-triangle';
    else if (tipo === 'info') icono = 'fa-info-circle';
    
    notificacion.innerHTML = `
        <i class="fa-solid ${icono}"></i>
        <span>${mensaje}</span>
        <button class="cerrar-mensaje">&times;</button>
    `;
    
    document.body.appendChild(notificacion);
    
    const cerrarBtn = notificacion.querySelector('.cerrar-mensaje');
    cerrarBtn.addEventListener('click', () => notificacion.remove());
    
    setTimeout(() => {
        if (document.body.contains(notificacion)) {
            notificacion.style.opacity = '0';
            setTimeout(() => notificacion.remove(), 300);
        }
    }, duracion);
};

window.mostrarMensajeConfirmacion = window.mostrarNotificacion;

// ====== GESTIÓN DE SECCIONES ======
window.mostrarSeccion = function(id) {
    console.log('Cambiando a sección:', id);
    
    // 1. Ocultar todas las secciones
    document.querySelectorAll(".seccion").forEach(s => {
        s.classList.remove("visible");
    });
    
    // 2. Mostrar la sección seleccionada
    const el = document.getElementById(id);
    if (el) {
        el.classList.add("visible");
    }
    
    // 3. Actualizar botones del menú principal
    document.querySelectorAll(".menu-btn[data-seccion]").forEach(b => {
        b.classList.remove("activo");
        if (b.dataset.seccion === id) {
            b.classList.add("activo");
        }
    });
    
    // 4. Manejar botón de usuarios
    const btnUsuarios = document.getElementById("btnUsuarios");
    if (btnUsuarios) {
        if (id === 'usuarios') {
            btnUsuarios.classList.add("activo");
        } else {
            btnUsuarios.classList.remove("activo");
            // Cerrar submenú de usuarios si está abierto
            const submenu = document.getElementById("submenuUsuarios");
            if (submenu && submenu.classList.contains('abierto')) {
                submenu.classList.remove('abierto');
                submenu.style.maxHeight = "0";
            }
        }
    }
    
    // 5. Manejar botón de propiedades
    const btnPropiedades = document.getElementById("btnPropiedadesMenu");
    if (btnPropiedades) {
        if (['agregarpropiedad', 'propiedadespublicadas'].includes(id)) {
            btnPropiedades.classList.add("activo");
        } else {
            btnPropiedades.classList.remove("activo");
            // Cerrar submenú de propiedades si está abierto
            const submenu = document.getElementById("submenuPropiedades");
            if (submenu && submenu.classList.contains('abierto')) {
                submenu.classList.remove('abierto');
                submenu.style.maxHeight = "0";
            }
        }
    }
    
    // 6. Actualizar URL
    const url = new URL(window.location);
    url.searchParams.set('seccion', id);
    window.history.pushState({}, '', url.toString());
};

// ====== MOSTRAR TABLA DE USUARIOS ======
window.mostrarTablaUsuarios = function(tablaId) {
    console.log('Mostrando tabla de usuarios:', tablaId);
    
    // 1. Asegurar que estamos en la sección de usuarios
    window.mostrarSeccion('usuarios');
    
    // 2. Ocultar todas las tablas de usuarios
    document.querySelectorAll('.contenedor-tabla-usuarios').forEach(c => {
        c.style.display = 'none';
    });
    
    // 3. Ocultar sección de logs
    const seccionLogs = document.getElementById('logs');
    if (seccionLogs) {
        seccionLogs.classList.remove('visible');
    }
    
    // 4. Si es logs, mostrar sección de logs
    if (tablaId === 'logs') {
        if (seccionLogs) {
            seccionLogs.classList.add('visible');
            document.getElementById('usuarios')?.classList.remove('visible');
        }
        
        const titulo = document.getElementById("tituloUsuarios");
        if (titulo) titulo.textContent = 'Usuarios - Logs';
        
        // Actualizar botones activos del submenú
        document.querySelectorAll("#submenuUsuarios .submenu-btn").forEach(x => x.classList.remove("activo"));
        document.querySelector(`#submenuUsuarios .submenu-btn[data-tabla="logs"]`)?.classList.add("activo");
        return;
    }
    
    // 5. Mostrar la tabla seleccionada
    const contenedorId = 'contenedor' + tablaId.charAt(0).toUpperCase() + tablaId.slice(1);
    const contenedor = document.getElementById(contenedorId);
    
    if (contenedor) {
        contenedor.style.display = 'block';
        
        // Actualizar título
        const titulo = document.getElementById("tituloUsuarios");
        if (titulo) {
            const nombres = {
                'admins': 'Administradores',
                'propietarios': 'Propietarios',
                'visitantes': 'Visitantes'
            };
            titulo.textContent = `Usuarios - ${nombres[tablaId] || tablaId}`;
        }
        
        // Actualizar botones activos del submenú
        document.querySelectorAll("#submenuUsuarios .submenu-btn").forEach(x => x.classList.remove("activo"));
        document.querySelector(`#submenuUsuarios .submenu-btn[data-tabla="${tablaId}"]`)?.classList.add("activo");
        
        // Mostrar/ocultar botón de agregar según permisos
        const btnAgregar = document.getElementById('btnAgregarUsuario');
        if (btnAgregar) {
            btnAgregar.style.display = (tablaId === 'admins' && !esSuperadmin) ? 'none' : 'flex';
        }
    }
};

// ====== INICIALIZACIÓN PRINCIPAL ======
document.addEventListener('DOMContentLoaded', function() {
    
    // ====== VARIABLES GLOBALES ======
    csrfToken = document.getElementById('csrf_token')?.value || '';
    esSuperadmin = document.getElementById('es_superadmin')?.value === '1';
    
    // ====== BOTÓN DE INICIO ======
    const btnInicio = document.getElementById('btnInicio');
    if (btnInicio) {
        btnInicio.addEventListener('click', function(e) {
            e.preventDefault();
            window.mostrarSeccion('inicio');
        });
    }

    // ====== MENÚ USUARIOS ======
    function inicializarMenuUsuarios() {
        const btnUsuarios = document.getElementById("btnUsuarios");
        const submenu = document.getElementById("submenuUsuarios");
        if (!btnUsuarios || !submenu) return;
        
        let menuAbierto = false;
        
        function toggleMenu() {
            menuAbierto = !menuAbierto;
            if (menuAbierto) {
                submenu.classList.add("abierto");
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                btnUsuarios.classList.add("activo");
            } else {
                submenu.classList.remove("abierto");
                submenu.style.maxHeight = "0";
                btnUsuarios.classList.remove("activo");
            }
        }
        
        btnUsuarios.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });
        
        // Opciones del submenú
        document.querySelectorAll("#submenuUsuarios .submenu-btn").forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                const tablaId = btn.dataset.tabla;
                
                // Cerrar menú en móvil
                if (window.innerWidth <= 768) {
                    if (menuAbierto) toggleMenu();
                }
                
                // Mostrar la tabla correspondiente
                window.mostrarTablaUsuarios(tablaId);
                
                // Actualizar URL
                const url = new URL(window.location);
                url.searchParams.set('seccion', 'usuarios');
                url.searchParams.set('tabla', tablaId);
                window.history.pushState({}, '', url.toString());
            });
        });
        
        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!btnUsuarios.contains(e.target) && !submenu.contains(e.target) && menuAbierto) {
                menuAbierto = false;
                submenu.classList.remove("abierto");
                submenu.style.maxHeight = "0";
                btnUsuarios.classList.remove("activo");
            }
        });
    }

    // ====== MENÚ PROPIEDADES ======
    function inicializarMenuPropiedades() {
        const btnPropiedades = document.getElementById("btnPropiedadesMenu");
        const submenu = document.getElementById("submenuPropiedades");
        if (!btnPropiedades || !submenu) return;
        
        let menuAbierto = false;
        
        function toggleMenu() {
            menuAbierto = !menuAbierto;
            if (menuAbierto) {
                submenu.classList.add("abierto");
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                btnPropiedades.classList.add("activo");
            } else {
                submenu.classList.remove("abierto");
                submenu.style.maxHeight = "0";
                btnPropiedades.classList.remove("activo");
            }
        }
        
        btnPropiedades.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });
        
        // Opciones del submenú
        document.querySelectorAll("#submenuPropiedades .submenu-btn").forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                const seccionId = btn.dataset.seccion;
                
                // Cerrar menú en móvil
                if (window.innerWidth <= 768) {
                    if (menuAbierto) toggleMenu();
                }
                
                // Mostrar la sección
                window.mostrarSeccion(seccionId);
                
                // Actualizar URL
                const url = new URL(window.location);
                url.searchParams.set('seccion', seccionId);
                window.history.pushState({}, '', url.toString());
            });
        });
        
        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!btnPropiedades.contains(e.target) && !submenu.contains(e.target) && menuAbierto) {
                menuAbierto = false;
                submenu.classList.remove("abierto");
                submenu.style.maxHeight = "0";
                btnPropiedades.classList.remove("activo");
            }
        });
        
        // Responsive
        function ajustarMenu() {
            if (window.innerWidth <= 768) {
                submenu.style.transition = 'max-height 0.3s ease';
                if (!menuAbierto) submenu.style.maxHeight = "0";
            } else {
                submenu.style.transition = '';
                submenu.style.maxHeight = "";
            }
        }
        ajustarMenu();
        window.addEventListener('resize', ajustarMenu);
    }

    // ====== BUSCADOR DE LOGS ======
    function inicializarFiltrosLogs() {
        const searchLogs = document.getElementById('searchLogs');
        const filterToday = document.getElementById('filterToday');
        const clearFilters = document.getElementById('clearFilters');
        const tablaLogs = document.getElementById('tablaLogs');
        const tbody = tablaLogs?.querySelector('tbody');
        
        if (!tablaLogs || !tbody) return;
        
        let timeoutId;
        let paginaActual = 1;
        let totalPaginas = typeof GLOBAL_TOTAL_PAGINAS_LOGS !== 'undefined' ? GLOBAL_TOTAL_PAGINAS_LOGS : 1;
        let buscando = false;
        
        async function buscarLogs(texto, soloHoy = false) {
            if (buscando) return;
            buscando = true;
            
            tbody.innerHTML = `<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-spinner fa-spin"></i><p>Buscando...</p></td></tr>`;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'buscar_logs');
                formData.append('csrf_token', csrfToken);
                formData.append('busqueda', texto);
                formData.append('solo_hoy', soloHoy ? '1' : '0');
                formData.append('pagina', paginaActual);
                
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    mostrarResultadosLogs(data.logs, data.pagina, data.total_paginas);
                    totalPaginas = data.total_paginas;
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-exclamation-triangle"></i><p>Error</p></td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-exclamation-triangle"></i><p>Error de conexión</p></td></tr>`;
            } finally {
                buscando = false;
            }
        }
        
        function mostrarResultadosLogs(logs, pagina, totalPag) {
            if (!logs || logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-search"></i><h4>No hay resultados</h4></td></tr>`;
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                html += `<tr>
                    <td>${escapeHtml(log.usuario_nombre)}</td>
                    <td><span class="rol-badge rol-${escapeHtml(log.rol)}">${escapeHtml(log.rol)}</span></td>
                    <td>${escapeHtml(log.accion)}</td>
                    <td>${escapeHtml(log.fecha_simple)}</td>
                    <td><span class="hora-log">${escapeHtml(log.hora)}</span></td>
                </tr>`;
            });
            tbody.innerHTML = html;
            
            const infoPagina = document.querySelector('.logs-paginacion .info-pagina');
            if (infoPagina) infoPagina.textContent = `Pág. ${pagina} de ${totalPag}`;
            actualizarBotonesPaginacionLogs(pagina, totalPag);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        searchLogs?.addEventListener('input', function() {
            clearTimeout(timeoutId);
            const texto = this.value.trim();
            const soloHoy = filterToday?.classList.contains('active') || false;
            timeoutId = setTimeout(() => {
                paginaActual = 1;
                buscarLogs(texto, soloHoy);
            }, 500);
        });
        
        filterToday?.addEventListener('click', function() {
            this.classList.toggle('active');
            const texto = searchLogs?.value.trim() || '';
            const soloHoy = this.classList.contains('active');
            paginaActual = 1;
            buscarLogs(texto, soloHoy);
        });
        
        clearFilters?.addEventListener('click', function() {
            if (searchLogs) searchLogs.value = '';
            filterToday?.classList.remove('active');
            paginaActual = 1;
            buscarLogs('', false);
        });
        
        function actualizarBotonesPaginacionLogs(pagina, totalPag) {
            const prevBtn = document.querySelector('.logs-paginacion .pagina-btn:first-child');
            const nextBtn = document.querySelector('.logs-paginacion .pagina-btn:last-child');
            
            if (prevBtn) {
                if (pagina <= 1) {
                    prevBtn.classList.add('disabled');
                    prevBtn.onclick = null;
                } else {
                    prevBtn.classList.remove('disabled');
                    prevBtn.onclick = () => {
                        paginaActual = pagina - 1;
                        const texto = searchLogs?.value.trim() || '';
                        const soloHoy = filterToday?.classList.contains('active') || false;
                        buscarLogs(texto, soloHoy);
                    };
                }
            }
            
            if (nextBtn) {
                if (pagina >= totalPag) {
                    nextBtn.classList.add('disabled');
                    nextBtn.onclick = null;
                } else {
                    nextBtn.classList.remove('disabled');
                    nextBtn.onclick = () => {
                        paginaActual = pagina + 1;
                        const texto = searchLogs?.value.trim() || '';
                        const soloHoy = filterToday?.classList.contains('active') || false;
                        buscarLogs(texto, soloHoy);
                    };
                }
            }
        }
    }

    // ====== MANEJO DE ESTADO DE USUARIOS ======
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('toggle-estado') || e.target.classList.contains('slider') || e.target.closest('.switch')) {
            
            let toggle;
            if (e.target.classList.contains('toggle-estado')) toggle = e.target;
            else if (e.target.classList.contains('slider')) toggle = e.target.previousElementSibling;
            else if (e.target.closest('.switch')) toggle = e.target.closest('.switch').querySelector('.toggle-estado');
            
            if (!toggle) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const id = toggle.dataset.id;
            const rol = toggle.dataset.rol;
            const estadoActual = toggle.checked ? 1 : 0;
            const nuevoEstado = estadoActual ? 0 : 1;
            const fila = toggle.closest('tr');
            const nombreUsuario = fila.querySelector('.usuario-info')?.textContent.trim() || 'Usuario';
            const estadoTexto = toggle.parentElement.querySelector('.estado-texto');
            
            const mensaje = nuevoEstado === 0 ? `¿DESHABILITAR a "${nombreUsuario}"?` : `¿HABILITAR a "${nombreUsuario}"?`;
            if (!confirm(mensaje)) return;
            
            const textoOriginal = estadoTexto?.textContent || '';
            if (estadoTexto) estadoTexto.textContent = 'Cambiando...';
            toggle.disabled = true;
            
            const datos = new URLSearchParams();
            datos.append('accion', 'cambiar_estado');
            datos.append('csrf_token', csrfToken);
            datos.append('id', id);
            datos.append('rol', rol);
            datos.append('estado', nuevoEstado);
            
            fetch('indexadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: datos.toString()
            })
            .then(response => response.json())
            .then(data => {
                toggle.disabled = false;
                if (data.success) {
                    toggle.checked = nuevoEstado === 1;
                    if (estadoTexto) estadoTexto.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';
                    window.mostrarNotificacion(`Usuario ${nuevoEstado === 1 ? 'habilitado' : 'deshabilitado'}`, 'success');
                    actualizarContadorActivos();
                } else {
                    toggle.checked = estadoActual === 1;
                    if (estadoTexto) estadoTexto.textContent = textoOriginal;
                    window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            })
            .catch(error => {
                toggle.disabled = false;
                toggle.checked = estadoActual === 1;
                if (estadoTexto) estadoTexto.textContent = textoOriginal;
                window.mostrarNotificacion('Error de conexión', 'error');
            });
        }
    });

    function actualizarContadorActivos() {
        const activeToggles = document.querySelectorAll('.toggle-estado:checked');
        const totalElement = document.getElementById('totalUsuariosActivos');
        if (totalElement) totalElement.textContent = activeToggles.length;
    }

    // ====== MODAL AGREGAR USUARIO ======
    function inicializarModalAgregarUsuario() {
        const btnAgregar = document.getElementById('btnAgregarUsuario');
        const modal = document.getElementById('modalAgregarUsuario');
        const cancelar = document.getElementById('cancelarAgregarUsuario');
        const cerrar = modal?.querySelector('.cerrar');
        const form = document.getElementById('formAgregarUsuario');
        
        if (!btnAgregar || !modal || !form) return;
        
        btnAgregar.addEventListener('click', () => {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
        
        function cerrarModal() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            form.reset();
            limpiarErrores();
        }
        
        cancelar?.addEventListener('click', cerrarModal);
        cerrar?.addEventListener('click', cerrarModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });
        
        function limpiarErrores() {
            document.querySelectorAll('#formAgregarUsuario .form-error').forEach(e => {
                e.textContent = '';
                e.classList.remove('active');
            });
        }
        
        function mostrarError(campo, mensaje) {
            const error = document.getElementById(`errorAgregar${campo}`);
            if (error) {
                error.textContent = mensaje;
                error.classList.add('active');
            }
        }
        
        function validarFormulario(formData) {
            let valido = true;
            limpiarErrores();
            
            const nombre = formData.get('nombre')?.trim();
            if (!nombre || nombre.length < 2) {
                mostrarError('Nombre', 'Mínimo 2 caracteres');
                valido = false;
            }
            
            const correo = formData.get('correo')?.trim();
            if (!correo) {
                mostrarError('Correo', 'Requerido');
                valido = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                mostrarError('Correo', 'Inválido');
                valido = false;
            }
            
            if (!formData.get('rol')) {
                mostrarError('Rol', 'Selecciona un rol');
                valido = false;
            }
            
            const pass = formData.get('password');
            if (!pass || pass.length < 8) {
                mostrarError('Password', 'Mínimo 8 caracteres');
                valido = false;
            }
            
            if (pass !== formData.get('confirm_password')) {
                mostrarError('ConfirmPassword', 'No coinciden');
                valido = false;
            }
            
            return valido;
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            if (!validarFormulario(formData)) return;
            
            const submitBtn = document.getElementById('submitAgregarUsuario');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creando...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    window.mostrarNotificacion(data.message, 'success');
                    cerrarModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    if (data.errors) data.errors.forEach(err => window.mostrarNotificacion(err, 'error'));
                    else window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            } catch (error) {
                window.mostrarNotificacion('Error al conectar', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // ====== MODAL EDITAR USUARIO ======
    function inicializarModalEditar() {
        const modal = document.getElementById("modalEditar");
        if (!modal) return;
        
        document.addEventListener('click', (e) => {
            if (e.target.closest('.editarBtn')) {
                const btn = e.target.closest('.editarBtn');
                const id = btn.dataset.id;
                const rol = btn.dataset.rol;
                const fila = btn.closest("tr");
                const nombre = fila.cells[0].innerText.trim();
                const correo = fila.cells[1].innerText.trim();
                
                document.getElementById("editId").value = id;
                document.getElementById("editRol").value = rol;
                document.getElementById("editNombre").value = nombre;
                document.getElementById("editCorreo").value = correo;
                
                const displayRol = document.getElementById("displayRol");
                const icono = displayRol.querySelector('i');
                if (icono) {
                    const iconos = { 'admin': 'fa-user-shield', 'propietario': 'fa-house-user', 'visitante': 'fa-user' };
                    icono.className = `fa-solid ${iconos[rol] || 'fa-user-shield'}`;
                }
                
                const span = displayRol.querySelector('span');
                if (span) {
                    span.textContent = rol === 'admin' ? 'Administrador' : 
                                     rol === 'propietario' ? 'Propietario' : 'Visitante';
                }
                
                modal.style.display = "flex";
                document.body.style.overflow = 'hidden';
            }
        });
        
        function cerrarModal() {
            modal.style.display = "none";
            document.body.style.overflow = '';
        }
        
        modal.querySelectorAll('.cerrar, .btn-cancelar').forEach(btn => btn.addEventListener('click', cerrarModal));
        window.addEventListener("click", (e) => { if (e.target === modal) cerrarModal(); });
    }

    // ====== MODAL VER DETALLES ======
    function inicializarModalDetalles() {
        const modal = document.getElementById('modalVerDetalles');
        const cerrar = document.getElementById('cerrarDetalles');
        const editarDesde = document.getElementById('editarDesdeDetalles');
        const cerrarX = modal?.querySelector('.cerrar');
        
        if (!modal) return;
        
        window.abrirModalDetalles = async function(id, rol, nombre, correo) {
            if (!id || !rol) return;
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            const detallesNombre = document.getElementById('detallesNombre');
            const detallesRol = document.getElementById('detallesRol');
            const iconoElement = document.getElementById('detallesIcono');
            
            if (detallesNombre) detallesNombre.textContent = nombre || 'Cargando...';
            
            let rolTexto = { 'admin': 'Administrador', 'propietario': 'Propietario', 'visitante': 'Visitante' }[rol] || rol;
            let rolIcono = { 'admin': 'fa-user-shield', 'propietario': 'fa-house-user', 'visitante': 'fa-user' }[rol] || 'fa-user';
            
            if (detallesRol) detallesRol.textContent = rolTexto;
            if (iconoElement) iconoElement.className = `fa-solid ${rolIcono}`;
            
            const detallesContent = document.getElementById('detallesContent');
            if (detallesContent) {
                detallesContent.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando...</p></div>`;
            }
            
            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_detalles_usuario');
                formData.append('csrf_token', csrfToken);
                formData.append('id', id);
                formData.append('rol', rol);
                
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success && data.usuario) {
                    const usuario = data.usuario;
                    const estadisticas = data.estadisticas || {};
                    
                    let html = `
                        <div class="detalles-header">
                            <div class="avatar-detalles"><i class="fa-solid ${rolIcono}"></i></div>
                            <div class="detalles-titulo">
                                <h4>${usuario.nombre || nombre}</h4>
                                <span class="rol-badge rol-${rol}">${rolTexto}</span>
                            </div>
                        </div>
                        <div class="detalles-info">
                            <div class="info-item"><span class="info-label">ID:</span><span class="info-value">${usuario.id}</span></div>
                            <div class="info-item"><span class="info-label">Nombre:</span><span class="info-value">${usuario.nombre || 'N/A'}</span></div>
                            <div class="info-item"><span class="info-label">Correo:</span><span class="info-value">${usuario.correo || 'N/A'}</span></div>
                    `;
                    
                    const estado = usuario.estado == 1 ? 'Activo' : 'Inactivo';
                    const estadoClase = usuario.estado == 1 ? 'estado-activo' : 'estado-inactivo';
                    html += `<div class="info-item"><span class="info-label">Estado:</span><span class="info-value ${estadoClase}">${estado}</span></div>`;
                    
                    if (usuario.telefono) html += `<div class="info-item"><span class="info-label">Teléfono:</span><span class="info-value">${usuario.telefono}</span></div>`;
                    
                    if (rol === 'propietario') {
                        if (usuario.dni) html += `<div class="info-item"><span class="info-label">DNI:</span><span class="info-value">${usuario.dni}</span></div>`;
                        if (usuario.sexo) html += `<div class="info-item"><span class="info-label">Sexo:</span><span class="info-value">${usuario.sexo}</span></div>`;
                        if (usuario.fecha_nac) {
                            const fecha = new Date(usuario.fecha_nac).toLocaleDateString('es-ES');
                            html += `<div class="info-item"><span class="info-label">Nacimiento:</span><span class="info-value">${fecha}</span></div>`;
                        }
                    }
                    
                    if (usuario.fecha_creacion) {
                        const fecha = new Date(usuario.fecha_creacion);
                        html += `<div class="info-item"><span class="info-label">Registro:</span><span class="info-value">${fecha.toLocaleDateString('es-ES')} ${fecha.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'})}</span></div>`;
                    }
                    
                    html += `</div>`;
                    
                    if (Object.keys(estadisticas).length > 0) {
                        html += `<div class="detalles-estadisticas"><h4><i class="fa-solid fa-chart-bar"></i> Estadísticas</h4><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;">`;
                        
                        if (estadisticas.propiedades !== undefined) {
                            html += `<div style="background:#f3f4f6;padding:15px;border-radius:8px;text-align:center;"><i class="fa-solid fa-building" style="color:#82b16d;"></i><div style="font-weight:bold;">${estadisticas.propiedades}</div><div style="font-size:0.85em;">Propiedades</div></div>`;
                        }
                        
                        if (estadisticas.favoritos !== undefined) {
                            html += `<div style="background:#f3f4f6;padding:15px;border-radius:8px;text-align:center;"><i class="fa-solid fa-heart" style="color:#ef4444;"></i><div style="font-weight:bold;">${estadisticas.favoritos}</div><div style="font-size:0.85em;">Favoritos</div></div>`;
                        }
                        
                        if (estadisticas.opiniones !== undefined) {
                            html += `<div style="background:#f3f4f6;padding:15px;border-radius:8px;text-align:center;"><i class="fa-solid fa-star" style="color:#f59e0b;"></i><div style="font-weight:bold;">${estadisticas.opiniones}</div><div style="font-size:0.85em;">Opiniones</div></div>`;
                        }
                        
                        if (estadisticas.logs !== undefined) {
                            html += `<div style="background:#f3f4f6;padding:15px;border-radius:8px;text-align:center;"><i class="fa-solid fa-tasks" style="color:#3b82f6;"></i><div style="font-weight:bold;">${estadisticas.logs}</div><div style="font-size:0.85em;">Acciones</div></div>`;
                        }
                        
                        if (estadisticas.actividad_reciente && estadisticas.actividad_reciente.length) {
                            html += `<div style="background:#f3f4f6;padding:15px;border-radius:8px;text-align:center;"><i class="fa-solid fa-history" style="color:#8b5cf6;"></i><div style="font-weight:bold;">${estadisticas.actividad_reciente.length}</div><div style="font-size:0.85em;">Actividades</div></div>`;
                        }
                        
                        html += `</div></div>`;
                    }
                    
                    document.getElementById('detallesContent').innerHTML = html;
                    modal.dataset.userId = id;
                    modal.dataset.userRol = rol;
                }
            } catch (error) {
                document.getElementById('detallesContent').innerHTML = `<div style="text-align:center;padding:40px;"><i class="fa-solid fa-exclamation-triangle"></i><p>Error</p></div>`;
            }
        };
        
        function cerrarModal() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        cerrar?.addEventListener('click', cerrarModal);
        cerrarX?.addEventListener('click', cerrarModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });
        
        editarDesde?.addEventListener('click', () => {
            const userId = modal.dataset.userId;
            const userRol = modal.dataset.userRol;
            if (userId && userRol) {
                document.querySelector(`.editarBtn[data-id="${userId}"][data-rol="${userRol}"]`)?.click();
            }
            cerrarModal();
        });
    }

    // ====== MODAL ELIMINAR USUARIO ======
    function inicializarModalEliminar() {
        let filaAEliminar = null;
        let idAEliminar = null;
        let rolAEliminar = null;
        
        document.addEventListener('click', (e) => {
            if (e.target.closest('.eliminarBtn')) {
                const btn = e.target.closest('.eliminarBtn');
                filaAEliminar = btn.closest("tr");
                idAEliminar = btn.dataset.id;
                rolAEliminar = btn.dataset.rol;
                const nombre = btn.dataset.nombre || filaAEliminar.cells[0].innerText.trim();
                
                document.getElementById("textoConfirmacion").innerHTML = `¿Eliminar a <strong>"${nombre}"</strong>?<br><small>Acción permanente</small>`;
                document.getElementById("modalConfirmarEliminar").style.display = "flex";
                document.body.style.overflow = 'hidden';
            }
        });
        
        document.getElementById("confirmarEliminar")?.addEventListener("click", async () => {
            if (!filaAEliminar || !idAEliminar || !rolAEliminar) return;
            
            const btn = document.getElementById("confirmarEliminar");
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Eliminando...';
            btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'eliminar_usuario');
                formData.append('csrf_token', csrfToken);
                formData.append('id', idAEliminar);
                formData.append('rol', rolAEliminar);
                
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    filaAEliminar.style.opacity = '0.5';
                    filaAEliminar.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        filaAEliminar.style.display = "none";
                        window.mostrarNotificacion(data.message, 'success');
                    }, 400);
                } else {
                    window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            } catch (error) {
                window.mostrarNotificacion('Error de conexión', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                document.getElementById("modalConfirmarEliminar").style.display = "none";
                document.body.style.overflow = '';
                filaAEliminar = idAEliminar = rolAEliminar = null;
            }
        });
        
        document.getElementById("cancelarEliminar")?.addEventListener("click", () => {
            document.getElementById("modalConfirmarEliminar").style.display = "none";
            document.body.style.overflow = '';
            filaAEliminar = idAEliminar = rolAEliminar = null;
        });
    }

    // ====== CERRAR SESIÓN ======
    function inicializarLogout() {
        const btnLogout = document.getElementById("btnLogout");
        const modal = document.getElementById("modalConfirmarLogout");
        
        if (!btnLogout || !modal) return;
        
        btnLogout.addEventListener("click", (e) => {
            e.preventDefault();
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        });
        
        document.getElementById("confirmarLogout")?.addEventListener("click", () => {
            window.location.href = '../database/logout.php';
        });
        
        document.getElementById("cancelarLogout")?.addEventListener("click", () => {
            modal.style.display = "none";
            document.body.style.overflow = '';
        });
        
        modal.querySelector('.cerrar')?.addEventListener("click", () => {
            modal.style.display = "none";
            document.body.style.overflow = '';
        });
    }

    // ====== FILTROS DE SOLICITUDES ======
    function inicializarFiltrosSolicitudes() {
        const buscador = document.getElementById('buscadorSolicitudes');
        const filtroFecha = document.getElementById('filtroFecha');
        const filtroPrioridad = document.getElementById('filtroPrioridad');
        const orden = document.getElementById('ordenSolicitudes');
        const aplicar = document.getElementById('aplicarFiltros');
        const limpiar = document.getElementById('limpiarFiltros');
        const refresh = document.getElementById('refreshSolicitudes');
        
        const contenedor = document.querySelector('.lista-solicitudes');
        if (!contenedor) return;
        
        const tarjetas = Array.from(contenedor.querySelectorAll('.tarjeta-solicitud'));
        
        function calcularDias(tarjeta) {
            const badge = tarjeta.querySelector('.badge-dias');
            const match = badge?.textContent.match(/(\d+)/);
            return match ? parseInt(match[1]) : 0;
        }
        
        function obtenerFecha(tarjeta) {
            const fechaEl = tarjeta.querySelector('.solicitud-fecha');
            const match = fechaEl?.textContent.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            return match ? new Date(match[3], match[2]-1, match[1]) : new Date(0);
        }
        
        function aplicarFiltros() {
            const textoBusq = buscador ? buscador.value.toLowerCase() : '';
            const fechaVal = filtroFecha?.value || 'todas';
            const prioridadVal = filtroPrioridad?.value || 'todas';
            const ordenVal = orden?.value || 'antiguas';
            
            let filtradas = tarjetas.filter(t => {
                if (textoBusq && !t.textContent.toLowerCase().includes(textoBusq)) return false;
                
                const dias = calcularDias(t);
                
                if (prioridadVal !== 'todas') {
                    if (prioridadVal === 'urgente' && dias < 7) return false;
                    if (prioridadVal === 'normal' && (dias < 3 || dias > 6)) return false;
                    if (prioridadVal === 'nuevas' && dias > 2) return false;
                }
                
                if (fechaVal !== 'todas') {
                    const fecha = obtenerFecha(t);
                    const hoy = new Date();
                    
                    if (fechaVal === 'hoy' && fecha.toDateString() !== hoy.toDateString()) return false;
                    if (fechaVal === 'semana') {
                        const inicioSemana = new Date(hoy.setDate(hoy.getDate() - hoy.getDay()));
                        if (fecha < inicioSemana) return false;
                    }
                    if (fechaVal === 'mes' && fecha.getMonth() !== new Date().getMonth()) return false;
                    if (fechaVal === 'antiguas') {
                        const sieteDias = new Date(Date.now() - 7*24*60*60*1000);
                        if (fecha > sieteDias) return false;
                    }
                }
                return true;
            });
            
            filtradas.sort((a, b) => {
                if (ordenVal === 'nuevas') return obtenerFecha(b) - obtenerFecha(a);
                if (ordenVal === 'antiguas') return obtenerFecha(a) - obtenerFecha(b);
                if (ordenVal === 'titulo') {
                    const titA = a.querySelector('h3')?.textContent.toLowerCase() || '';
                    const titB = b.querySelector('h3')?.textContent.toLowerCase() || '';
                    return titA.localeCompare(titB);
                }
                if (ordenVal === 'propietario') {
                    const propA = a.querySelector('.dato-item:first-child span')?.textContent.toLowerCase() || '';
                    const propB = b.querySelector('.dato-item:first-child span')?.textContent.toLowerCase() || '';
                    return propA.localeCompare(propB);
                }
                return 0;
            });
            
            tarjetas.forEach(t => t.style.display = 'none');
            filtradas.forEach((t, i) => {
                t.style.display = 'flex';
                t.style.order = i;
            });
            
            const urgentes = filtradas.filter(t => calcularDias(t) >= 7).length;
            let totalDias = 0;
            filtradas.forEach(t => totalDias += calcularDias(t));
            const promedio = filtradas.length ? Math.round(totalDias / filtradas.length) : 0;
            
            document.getElementById('solicitudesFiltradas').textContent = filtradas.length;
            document.getElementById('solicitudesUrgentes').textContent = urgentes;
            document.getElementById('promedioDias').textContent = promedio;
            document.getElementById('solicitudesHoy').textContent = filtradas.filter(t => obtenerFecha(t).toDateString() === new Date().toDateString()).length;
        }
        
        aplicar?.addEventListener('click', aplicarFiltros);
        
        limpiar?.addEventListener('click', () => {
            if (buscador) buscador.value = '';
            if (filtroFecha) filtroFecha.value = 'todas';
            if (filtroPrioridad) filtroPrioridad.value = 'todas';
            if (orden) orden.value = 'antiguas';
            aplicarFiltros();
        });
        
        refresh?.addEventListener('click', () => location.reload());
        
        buscador?.addEventListener('input', () => {
            clearTimeout(window.busquedaTimeout);
            window.busquedaTimeout = setTimeout(aplicarFiltros, 500);
        });
        
        [filtroFecha, filtroPrioridad, orden].forEach(s => s?.addEventListener('change', aplicarFiltros));
        
        setTimeout(() => {
            const urgentes = tarjetas.filter(t => calcularDias(t) >= 7).length;
            document.getElementById('solicitudesUrgentes').textContent = urgentes;
            let totalDias = 0;
            tarjetas.forEach(t => totalDias += calcularDias(t));
            document.getElementById('promedioDias').textContent = tarjetas.length ? Math.round(totalDias / tarjetas.length) : 0;
        }, 500);
    }

    // ====== SOLICITUDES (aprobación/rechazo) ======
    function inicializarSolicitudes() {
        const modalDetalles = document.getElementById('modalDetallesSolicitud');
        const modalRechazar = document.getElementById('modalRechazarSolicitud');
        const modalAprobar = document.getElementById('modalConfirmarAprobacion');
        
        if (!modalDetalles) return;
        
        let solicitudActual = null;
        let propiedadActual = null;
        
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.btn-ver-solicitud')) {
                e.preventDefault();
                const btn = e.target.closest('.btn-ver-solicitud');
                propiedadActual = parseInt(btn.dataset.id);
                await cargarDetalles(propiedadActual);
            }
        });
        
        async function cargarDetalles(id) {
            const content = document.getElementById('detallesSolicitudContent');
            content.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando...</p></div>`;
            modalDetalles.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            const formData = new FormData();
            formData.append('accion', 'obtener_detalles_solicitud');
            formData.append('csrf_token', csrfToken);
            formData.append('id_propiedad', id);
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    solicitudActual = data.propiedad;
                    mostrarDetalles(data.propiedad);
                } else {
                    content.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fa-solid fa-exclamation-triangle"></i><p>${data.error || 'Error'}</p></div>`;
                }
            } catch (error) {
                content.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fa-solid fa-exclamation-triangle"></i><p>Error de conexión</p></div>`;
            }
        }
        
        function mostrarDetalles(p) {
            const precio = p.precio_no_publicado ? 'No publicado' : '$' + new Intl.NumberFormat('es-AR').format(p.precio);
            
            let servicios = '';
            if (p.servicios_array?.length) {
                servicios = p.servicios_array.map(s => `<span style="background:#eef2ff;padding:5px 10px;border-radius:15px;"><i class="fa-solid fa-check"></i> ${s}</span>`).join(' ');
            }
            
            let imagenes = '';
            if (p.imagenes_array?.length) {
                imagenes = p.imagenes_array.map((img,i) => `
                    <div style="width:100px;height:70px;overflow:hidden;border-radius:6px;cursor:pointer;" onclick="window.open('../media/${img.ruta}','_blank')">
                        <img src="../media/${img.ruta}" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                `).join('');
            }
            
            const html = `
                <div>
                    <h3>${p.titulo}</h3>
                    <div style="display:flex;gap:20px;">
                        <div><img src="../media/${p.imagenes_array?.[0]?.ruta || ''}" style="width:150px;height:100px;object-fit:cover;"></div>
                        <div>
                            <p><strong>Propietario:</strong> ${p.propietario_nombre || 'N/A'}</p>
                            <p><strong>Precio:</strong> ${precio}</p>
                        </div>
                    </div>
                    <p><strong>Dirección:</strong> ${p.direccion}</p>
                    <p><strong>Descripción:</strong> ${p.descripcion || ''}</p>
                    ${servicios ? `<div><strong>Servicios:</strong> ${servicios}</div>` : ''}
                    ${imagenes ? `<div><strong>Imágenes:</strong><div style="display:flex;gap:10px;">${imagenes}</div></div>` : ''}
                </div>
            `;
            
            document.getElementById('detallesSolicitudContent').innerHTML = html;
            
            document.getElementById('btnRechazarSolicitud').onclick = () => {
                modalDetalles.style.display = 'none';
                abrirRechazar();
            };
            
            document.getElementById('btnAprobarSolicitud').onclick = () => {
                modalDetalles.style.display = 'none';
                abrirAprobar();
            };
        }
        
        function abrirRechazar() {
            if (!solicitudActual) return;
            document.getElementById('textoRechazar').textContent = `¿Rechazar "${solicitudActual.titulo}"?`;
            document.getElementById('motivoRechazo').value = '';
            document.getElementById('errorMotivoRechazo').textContent = '';
            modalRechazar.style.display = 'flex';
        }
        
        function abrirAprobar() {
            if (!solicitudActual) return;
            document.getElementById('textoConfirmarAprobacion').textContent = `¿Aprobar "${solicitudActual.titulo}"?`;
            modalAprobar.style.display = 'flex';
        }
        
        document.getElementById('confirmarRechazar')?.addEventListener('click', async () => {
            const motivo = document.getElementById('motivoRechazo').value.trim();
            if (!motivo) {
                document.getElementById('errorMotivoRechazo').textContent = 'Motivo requerido';
                return;
            }
            await procesarDecision('rechazar', motivo);
        });
        
        document.getElementById('confirmarAprobacion')?.addEventListener('click', async () => {
            await procesarDecision('aprobar');
        });
        
        async function procesarDecision(decision, motivo = '') {
            if (!solicitudActual || !propiedadActual) return;
            
            const btn = decision === 'aprobar' ? document.getElementById('confirmarAprobacion') : document.getElementById('confirmarRechazar');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('accion', 'gestionar_solicitud');
            formData.append('csrf_token', csrfToken);
            formData.append('id_propiedad', propiedadActual);
            formData.append('decision', decision);
            if (motivo) formData.append('motivo_rechazo', motivo);
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    window.mostrarNotificacion(data.message, 'success');
                    [modalRechazar, modalAprobar, modalDetalles].forEach(m => m.style.display = 'none');
                    document.body.style.overflow = '';
                    const tarjeta = document.querySelector(`.tarjeta-solicitud[data-id="${propiedadActual}"]`);
                    if (tarjeta) {
                        tarjeta.style.opacity = '0.5';
                        tarjeta.style.transform = 'translateX(20px)';
                        setTimeout(() => tarjeta.remove(), 300);
                    }
                    setTimeout(() => location.reload(), 1000);
                } else {
                    window.mostrarNotificacion(data.error || 'Error', 'error');
                    modalDetalles.style.display = 'flex';
                }
            } catch (error) {
                window.mostrarNotificacion('Error de conexión', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        
        const cerrarFuncs = [
            ['cancelarRechazar', modalRechazar],
            ['cancelarAprobacion', modalAprobar],
            ['cancelarRechazo', modalDetalles]
        ];
        
        cerrarFuncs.forEach(([id, modal]) => {
            document.getElementById(id)?.addEventListener('click', () => {
                modal.style.display = 'none';
                if (id === 'cancelarRechazo') document.body.style.overflow = '';
                else modalDetalles.style.display = 'flex';
            });
        });
        
        [modalDetalles, modalRechazar, modalAprobar].forEach(m => {
            m?.addEventListener('click', (e) => {
                if (e.target === m) {
                    m.style.display = 'none';
                    if (m === modalDetalles) document.body.style.overflow = '';
                }
            });
            m?.querySelector('.cerrar')?.addEventListener('click', () => {
                m.style.display = 'none';
                if (m === modalDetalles) document.body.style.overflow = '';
            });
        });
    }

    // ====== PROPIEDADES PUBLICADAS ======
    function inicializarPropiedades() {
        const filtro = document.getElementById('filtroPropiedades');
        
        filtro?.addEventListener('change', function() {
            const valor = this.value;
            document.querySelectorAll('.tarjeta-propiedad').forEach(t => {
                t.style.display = (valor === 'todas' || t.dataset.estado === valor) ? 'flex' : 'none';
            });
        });
        
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.btn-ocultar-propiedad, .btn-mostrar-propiedad')) {
                e.preventDefault();
                const btn = e.target.closest('.btn-ocultar-propiedad, .btn-mostrar-propiedad');
                const id = parseInt(btn.dataset.id);
                const titulo = btn.dataset.titulo;
                const accion = btn.classList.contains('btn-ocultar-propiedad') ? 'ocultar' : 'mostrar';
                
                if (confirm(`¿${accion === 'ocultar' ? 'Ocultar' : 'Mostrar'} "${titulo}"?`)) {
                    await gestionarPropiedad(id, accion);
                }
            }
        });
        
        async function gestionarPropiedad(id, accion) {
            const formData = new FormData();
            formData.append('accion', 'gestionar_propiedad');
            formData.append('csrf_token', csrfToken);
            formData.append('id_propiedad', id);
            formData.append('tipo_accion', accion);
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    window.mostrarNotificacion(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            } catch (error) {
                window.mostrarNotificacion('Error de conexión', 'error');
            }
        }
    }

    // ====== SUBIR PROPIEDAD ======
    function inicializarSubirPropiedad() {
        const form = document.getElementById('formulario-propiedad-admin');
        if (!form) return;
        
        const checkboxNoPublicar = document.getElementById('no-decirlo');
        const inputPrecio = document.getElementById('precio');
        
        checkboxNoPublicar?.addEventListener('change', function() {
            inputPrecio.required = !this.checked;
            inputPrecio.placeholder = this.checked ? 'Opcional' : '120000';
        });
        
        const areaSubida = document.getElementById('areaSubidaArchivosAdmin');
        const inputImagenes = areaSubida?.querySelector('input[type="file"]');
        const listaArchivos = document.getElementById('listaArchivosAdmin');
        
        if (areaSubida && inputImagenes) {
            areaSubida.addEventListener('click', () => inputImagenes.click());
            areaSubida.addEventListener('dragover', (e) => { e.preventDefault(); areaSubida.style.backgroundColor = '#f0f8ff'; });
            areaSubida.addEventListener('dragleave', () => { areaSubida.style.backgroundColor = ''; });
            areaSubida.addEventListener('drop', (e) => {
                e.preventDefault();
                areaSubida.style.backgroundColor = '';
                if (e.dataTransfer.files.length) {
                    inputImagenes.files = e.dataTransfer.files;
                    mostrarArchivos(inputImagenes.files);
                }
            });
            inputImagenes.addEventListener('change', function() { mostrarArchivos(this.files); });
        }
        
        function mostrarArchivos(files) {
            if (!listaArchivos) return;
            listaArchivos.innerHTML = '';
            Array.from(files).forEach((file, i) => {
                const tamano = file.size > 1024*1024 ? (file.size/(1024*1024)).toFixed(2) + ' MB' : (file.size/1024).toFixed(2) + ' KB';
                const item = document.createElement('div');
                item.className = 'item-archivo';
                item.innerHTML = `<i class="fa-solid fa-image"></i><div class="nombre-archivo">${file.name}</div><div class="tamano-archivo">${tamano}</div><i class="fa-solid fa-times eliminar-archivo" data-index="${i}"></i>`;
                item.querySelector('.eliminar-archivo').addEventListener('click', function() {
                    const dt = new DataTransfer();
                    Array.from(inputImagenes.files).forEach((f, idx) => {
                        if (idx !== parseInt(this.dataset.index)) dt.items.add(f);
                    });
                    inputImagenes.files = dt.files;
                    mostrarArchivos(dt.files);
                });
                listaArchivos.appendChild(item);
            });
        }
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            document.getElementById('modalConfirmarSubirPropiedad').style.display = 'flex';
        });
        
        document.getElementById('confirmarSubirPropiedad')?.addEventListener('click', async () => {
            const btn = document.getElementById('confirmarSubirPropiedad');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publicando...';
            btn.disabled = true;
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                document.getElementById('modalConfirmarSubirPropiedad').style.display = 'none';
                
                if (data.success) {
                    window.mostrarNotificacion(data.message, 'success');
                    window.limpiarFormularioPropiedad();
                    setTimeout(() => window.location.href = '?seccion=propiedadespublicadas', 1500);
                } else {
                    if (data.errors) data.errors.forEach(e => window.mostrarNotificacion(e, 'error'));
                    else window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            } catch (error) {
                window.mostrarNotificacion('Error de conexión', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
        
        document.getElementById('cancelarSubirPropiedad')?.addEventListener('click', () => {
            document.getElementById('modalConfirmarSubirPropiedad').style.display = 'none';
        });
        
        window.limpiarFormularioPropiedad = function() {
            form.reset();
            if (listaArchivos) listaArchivos.innerHTML = '';
            if (inputPrecio) inputPrecio.required = true;
        };
    }

    // ====== SERVICIOS ======
    function inicializarServicios() {
        const btnAgregar = document.getElementById('btnAgregarServicio');
        const modal = document.getElementById('modalAgregarServicio');
        const cancelar = document.getElementById('cancelarAgregarServicio');
        const form = document.getElementById('formAgregarServicio');
        
        if (!btnAgregar || !modal || !form) return;
        
        btnAgregar.addEventListener('click', () => {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
        
        function cerrarModal() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            form.reset();
        }
        
        cancelar?.addEventListener('click', cerrarModal);
        modal.querySelector('.cerrar')?.addEventListener('click', cerrarModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });
        
        const iconoPreview = document.getElementById('previewIcono');
        const iconoInput = document.getElementById('iconoServicio');
        
        iconoInput?.addEventListener('input', function() {
            if (iconoPreview) iconoPreview.className = this.value.trim() || 'fa-solid fa-star';
        });
        
        document.querySelectorAll('.icono-option').forEach(btn => {
            btn.addEventListener('click', function() {
                const icono = this.dataset.icono;
                if (iconoInput) iconoInput.value = icono;
                if (iconoPreview) iconoPreview.className = icono;
            });
        });
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitAgregarServicio');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Agregando...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('indexadmin.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    window.mostrarNotificacion(data.message, 'success');
                    cerrarModal();
                    actualizarListaServicios();
                } else {
                    if (data.errors) data.errors.forEach(e => window.mostrarNotificacion(e, 'error'));
                    else window.mostrarNotificacion(data.error || 'Error', 'error');
                }
            } catch (error) {
                window.mostrarNotificacion('Error de conexión', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        async function actualizarListaServicios() {
            const grid = document.getElementById('gridServicios');
            if (!grid) return;
            
            try {
                const response = await fetch('?accion=obtener_servicios&ajax=1');
                const data = await response.json();
                
                if (data.success) {
                    grid.innerHTML = data.servicios.length ? data.servicios.map(s => `
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="${s.nombre}">
                            <div class="item-servicio">
                                <i class="${s.icono}"></i>
                                <span>${s.nombre}</span>
                            </div>
                        </label>
                    `).join('') : '<p class="sin-servicios">No hay servicios</p>';
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        document.getElementById('btnRefreshServicios')?.addEventListener('click', actualizarListaServicios);
    }

    // ====== PAGINACIÓN ======
    window.cambiarPagina = function(tipo, nuevaPagina) {
        const tbody = document.getElementById(`tbody${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        const paginaActualEl = document.getElementById(`paginaActual${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        
        if (!tbody || !paginaActualEl) return;
        
        const oldHTML = tbody.innerHTML;
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando...</p></td></tr>`;
        
        const contenedor = document.getElementById(`contenedor${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        const botones = contenedor?.querySelectorAll('.pagina-btn');
        botones?.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; b.style.cursor = 'wait'; });
        
        const formData = new FormData();
        formData.append('accion', 'cambiar_pagina');
        formData.append('csrf_token', csrfToken);
        formData.append('tipo', tipo);
        formData.append('pagina', nuevaPagina);
        
        fetch('indexadmin.php', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(html => {
                if (html.trim()) {
                    tbody.innerHTML = html;
                    paginaActualEl.textContent = nuevaPagina;
                }
            })
            .catch(() => tbody.innerHTML = oldHTML)
            .finally(() => botones?.forEach(b => {
                b.disabled = false;
                b.style.opacity = '';
                b.style.cursor = '';
            }));
    };

    // ====== NOTIFICACIONES EN TIEMPO REAL ======
    function inicializarNotificaciones() {
        const btnSolicitudes = document.getElementById('btnSolicitudes');
        
        function verificarNuevas() {
            fetch('indexadmin.php?accion=contar_solicitudes&ajax=1')
                .then(r => r.json())
                .then(data => {
                    let badge = document.getElementById('badgeSolicitudes');
                    if (data.total > 0) {
                        if (badge) {
                            badge.textContent = data.total;
                        } else {
                            badge = document.createElement('span');
                            badge.className = 'badge-notificacion';
                            badge.id = 'badgeSolicitudes';
                            badge.textContent = data.total;
                            btnSolicitudes?.appendChild(badge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(console.error);
        }
        
        setInterval(verificarNuevas, 30000);
        verificarNuevas();
    }

    // ====== ACTUALIZAR HORA ======
    function iniciarReloj() {
        function actualizarHora() {
            const now = new Date();
            const timeEl = document.getElementById('currentTime');
            const updateEl = document.getElementById('lastUpdate');
            
            if (timeEl) {
                timeEl.textContent = now.toLocaleString('es-ES', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }
            
            if (updateEl) {
                updateEl.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            }
        }
        actualizarHora();
        setInterval(actualizarHora, 1000);
    }

    // ====== BUSCADORES DE USUARIOS ======
    function inicializarBuscadoresTablas() {
        ['admins', 'propietarios', 'visitantes'].forEach(tipo => {
            const contenedor = document.getElementById(`contenedor${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
            if (!contenedor) return;
            
            const buscador = contenedor.querySelector('.buscador-tabla');
            if (!buscador) return;
            
            const tbody = contenedor.querySelector('tbody');
            const filasOriginales = Array.from(tbody?.querySelectorAll('tr') || []);
            
            function buscarEnTabla(texto) {
                texto = texto.toLowerCase().trim();
                
                if (texto === '') {
                    filasOriginales.forEach(f => f.style.display = '');
                    contenedor.querySelectorAll('.sin-resultados-busqueda').forEach(el => el.remove());
                    return;
                }
                
                let algunaVisible = false;
                
                filasOriginales.forEach(fila => {
                    const nombre = fila.cells[0]?.textContent.toLowerCase() || '';
                    const correo = fila.cells[1]?.textContent.toLowerCase() || '';
                    const rol = fila.cells[2]?.textContent.toLowerCase() || '';
                    const estado = fila.cells[3]?.querySelector('.estado-texto')?.textContent.toLowerCase() || '';
                    
                    const coincide = nombre.includes(texto) || correo.includes(texto) || rol.includes(texto) || estado.includes(texto);
                    
                    if (coincide) {
                        fila.style.display = '';
                        algunaVisible = true;
                    } else {
                        fila.style.display = 'none';
                    }
                });
                
                contenedor.querySelectorAll('.sin-resultados-busqueda').forEach(el => el.remove());
                
                if (!algunaVisible && filasOriginales.length > 0) {
                    const filaMensaje = document.createElement('tr');
                    filaMensaje.className = 'sin-resultados-busqueda';
                    filaMensaje.innerHTML = `<td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-search"></i><h4>No hay resultados</h4><p>"${texto}"</p></td>`;
                    tbody?.appendChild(filaMensaje);
                }
            }
            
            let timeoutId;
            buscador.addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => buscarEnTabla(this.value), 300);
            });
        });
    }

    // ====== CORRECCIÓN PARA BOTONES DE SOLICITUDES Y ESTADÍSTICAS ======
// Forzar que los botones funcionen correctamente

// Botón de Solicitudes Pendientes
const btnSolicitudes = document.getElementById('btnSolicitudes');
if (btnSolicitudes) {
    // Eliminar event listeners anteriores
    const nuevoBtnSolicitudes = btnSolicitudes.cloneNode(true);
    btnSolicitudes.parentNode.replaceChild(nuevoBtnSolicitudes, btnSolicitudes);
    
    // Agregar nuevo event listener
    nuevoBtnSolicitudes.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Clic en Solicitudes Pendientes');
        window.mostrarSeccion('solicitudes');
    });
    console.log('✅ Botón de Solicitudes configurado');
}

// Botón de Estadísticas
const btnEstadisticas = document.getElementById('btnEstadisticas');
if (btnEstadisticas) {
    // Eliminar event listeners anteriores
    const nuevoBtnEstadisticas = btnEstadisticas.cloneNode(true);
    btnEstadisticas.parentNode.replaceChild(nuevoBtnEstadisticas, btnEstadisticas);
    
    // Agregar nuevo event listener
    nuevoBtnEstadisticas.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Clic en Estadísticas');
        window.mostrarSeccion('estadisticas');
    });
    console.log('✅ Botón de Estadísticas configurado');
}

// También asegurar que los botones de acciones rápidas funcionen
document.querySelectorAll('.action-btn[onclick]').forEach(btn => {
    // Los que ya tienen onclick funcionan, pero si alguno no, lo forzamos
    if (btn.getAttribute('onclick')?.includes('mostrarSeccion')) {
        // Ya funciona
    } else if (btn.id === 'quickRefresh') {
        // Botón de actualizar
        btn.addEventListener('click', function() {
            location.reload();
        });
    }
});

    // ====== EJECUTAR TODAS LAS INICIALIZACIONES ======
    inicializarMenuUsuarios();
    inicializarMenuPropiedades();
    inicializarModalAgregarUsuario();
    inicializarModalEditar();
    inicializarModalDetalles();
    inicializarModalEliminar();
    inicializarLogout();
    inicializarFiltrosLogs();
    inicializarFiltrosSolicitudes();
    inicializarSolicitudes();
    inicializarPropiedades();
    inicializarSubirPropiedad();
    inicializarServicios();
    inicializarNotificaciones();
    iniciarReloj();
    inicializarBuscadoresTablas();

    // Mostrar sección inicial
    const urlParams = new URLSearchParams(window.location.search);
    const seccionInicial = urlParams.get('seccion') || 'inicio';
    window.mostrarSeccion(seccionInicial);
    
    if (seccionInicial === 'usuarios') {
        setTimeout(() => window.mostrarTablaUsuarios(urlParams.get('tabla') || 'admins'), 100);
    }
    
    setTimeout(actualizarContadorActivos, 500);
    
    console.log('✅ Panel admin inicializado correctamente');
});