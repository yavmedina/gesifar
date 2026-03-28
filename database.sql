-- ============================================
-- GESIFAR - Sistema de Gestión de Farmacia Hospitalaria
-- Base de Datos
-- ============================================

DROP DATABASE IF EXISTS gesifar;
CREATE DATABASE gesifar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gesifar;

-- ============================================
-- TABLA: usuarios (sistema de login)
-- ============================================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    rol ENUM('admin', 'responsable_movimientos', 'responsable_stock', 'responsable_compras') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- ============================================
-- TABLA: profesion
-- ============================================
CREATE TABLE profesion (
    id_profesion INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: area
-- ============================================
CREATE TABLE area (
    id_area INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: profesional_solicitante
-- ============================================
CREATE TABLE profesional_solicitante (
    dni_profesional_solicitante VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    id_profesion INT NOT NULL,
    FOREIGN KEY (id_profesion) REFERENCES profesion(id_profesion)
);

-- ============================================
-- TABLA: estado
-- ============================================
CREATE TABLE estado (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: franja_horaria
-- ============================================
CREATE TABLE franja_horaria (
    id_franja_horaria INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: turno
-- ============================================
CREATE TABLE turno (
    id_turno INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL,
    id_franja_horaria INT NOT NULL,
    FOREIGN KEY (id_franja_horaria) REFERENCES franja_horaria(id_franja_horaria)
);

-- ============================================
-- TABLA: provincia
-- ============================================
CREATE TABLE provincia (
    id_provincia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: localidad
-- ============================================
CREATE TABLE localidad (
    id_localidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    id_provincia INT NOT NULL,
    FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia)
);

-- ============================================
-- TABLA: barrio
-- ============================================
CREATE TABLE barrio (
    id_barrio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    id_localidad INT NOT NULL,
    FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad)
);

-- ============================================
-- TABLA: domicilio
-- ============================================
CREATE TABLE domicilio (
    id_domicilio INT AUTO_INCREMENT PRIMARY KEY,
    calle VARCHAR(50) NOT NULL,
    numero VARCHAR(50) NOT NULL,
    id_barrio INT NOT NULL,
    FOREIGN KEY (id_barrio) REFERENCES barrio(id_barrio)
);

-- ============================================
-- TABLA: personal
-- ============================================
CREATE TABLE personal (
    dni_personal VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    telefono VARCHAR(50),
    id_turno INT NOT NULL,
    FOREIGN KEY (id_turno) REFERENCES turno(id_turno)
);

-- ============================================
-- TABLA: solicitud
-- ============================================
CREATE TABLE solicitud (
    id_solicitud INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    id_personal VARCHAR(50) NOT NULL,
    id_profesional_solicitante VARCHAR(50) NOT NULL,
    id_detalle_solicitud INT,
    id_area INT NOT NULL,
    id_estado INT NOT NULL,
    FOREIGN KEY (id_personal) REFERENCES personal(dni_personal),
    FOREIGN KEY (id_profesional_solicitante) REFERENCES profesional_solicitante(dni_profesional_solicitante),
    FOREIGN KEY (id_area) REFERENCES area(id_area),
    FOREIGN KEY (id_estado) REFERENCES estado(id_estado)
);

-- ============================================
-- TABLA: tipo_material
-- ============================================
CREATE TABLE tipo_material (
    id_tipo_material INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: forma_farmaceutica
-- ============================================
CREATE TABLE forma_farmaceutica (
    id_forma_farmaceutica INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: presentacion
-- ============================================
CREATE TABLE presentacion (
    id_presentacion INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: proveedor
-- ============================================
CREATE TABLE proveedor (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(50) NOT NULL,
    cuit VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(50),
    id_domicilio INT,
    id_contacto INT,
    FOREIGN KEY (id_domicilio) REFERENCES domicilio(id_domicilio)
);

-- ============================================
-- TABLA: contacto
-- ============================================
CREATE TABLE contacto (
    id_contacto INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL
);

-- ============================================
-- TABLA: material
-- ============================================
CREATE TABLE material (
    id_material INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_vencimiento DATE,
    id_forma_farmaceutica INT,
    id_presentacion INT,
    id_tipo_material INT NOT NULL,
    id_proveedor INT NOT NULL,
    FOREIGN KEY (id_forma_farmaceutica) REFERENCES forma_farmaceutica(id_forma_farmaceutica),
    FOREIGN KEY (id_presentacion) REFERENCES presentacion(id_presentacion),
    FOREIGN KEY (id_tipo_material) REFERENCES tipo_material(id_tipo_material),
    FOREIGN KEY (id_proveedor) REFERENCES proveedor(id_proveedor)
);

-- ============================================
-- TABLA: detalle_solicitud
-- ============================================
CREATE TABLE detalle_solicitud (
    id_detalle_solicitud INT AUTO_INCREMENT PRIMARY KEY,
    cantidad INT NOT NULL,
    id_material INT NOT NULL,
    FOREIGN KEY (id_material) REFERENCES material(id_material)
);

-- Actualizar FK de solicitud
ALTER TABLE solicitud 
ADD FOREIGN KEY (id_detalle_solicitud) REFERENCES detalle_solicitud(id_detalle_solicitud);

-- ============================================
-- TABLA: orden_compra
-- ============================================
CREATE TABLE orden_compra (
    id_orden_compra INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    id_detalle_orden_compra INT,
    id_proveedor INT NOT NULL,
    id_estado INT NOT NULL,
    FOREIGN KEY (id_proveedor) REFERENCES proveedor(id_proveedor),
    FOREIGN KEY (id_estado) REFERENCES estado(id_estado)
);

-- ============================================
-- TABLA: detalle_orden_compra
-- ============================================
CREATE TABLE detalle_orden_compra (
    id_orden_compra INT NOT NULL,
    id_material INT NOT NULL,
    cantidad INT NOT NULL,
    PRIMARY KEY (id_orden_compra, id_material),
    FOREIGN KEY (id_orden_compra) REFERENCES orden_compra(id_orden_compra),
    FOREIGN KEY (id_material) REFERENCES material(id_material)
);

-- ============================================
-- DATOS INICIALES
-- ============================================

-- Usuario administrador por defecto
INSERT INTO usuarios (username, password, nombre, email, rol) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@gesifar.com', 'admin');
-- Password: password

-- Estados básicos
INSERT INTO estado (descripcion) VALUES 
('Pendiente'),
('Entregada'),
('Cancelada'),
('Confirmada'),
('Rechazada');

-- Tipos de material
INSERT INTO tipo_material (descripcion) VALUES 
('Medicamento'),
('Insumo');

-- Franjas horarias
INSERT INTO franja_horaria (descripcion) VALUES 
('Mañana (06:00-14:00)'),
('Tarde (14:00-22:00)'),
('Noche (22:00-06:00)');

-- Turnos
INSERT INTO turno (descripcion, id_franja_horaria) VALUES 
('Turno Mañana', 1),
('Turno Tarde', 2),
('Turno Noche', 3);

-- Profesiones comunes
INSERT INTO profesion (descripcion) VALUES 
('Médico'),
('Enfermero'),
('Farmacéutico'),
('Técnico'),
('Administrativo');

-- Áreas comunes
INSERT INTO area (descripcion) VALUES 
('Emergencias'),
('Terapia Intensiva'),
('Clínica Médica'),
('Quirófano'),
('Pediatría'),
('Laboratorio');

-- Provincias (ejemplo Argentina)
INSERT INTO provincia (nombre) VALUES 
('Buenos Aires'),
('Córdoba'),
('Santa Fe'),
('Mendoza'),
('Tucumán');

-- Localidades de ejemplo
INSERT INTO localidad (nombre, id_provincia) VALUES 
('La Plata', 1),
('Mar del Plata', 1),
('Córdoba Capital', 2),
('Rosario', 3);

-- Barrios de ejemplo
INSERT INTO barrio (nombre, id_localidad) VALUES 
('Centro', 1),
('Norte', 1),
('Sur', 2);

-- Formas farmacéuticas
INSERT INTO forma_farmaceutica (descripcion) VALUES 
('Comprimido'),
('Cápsula'),
('Jarabe'),
('Inyectable'),
('Crema'),
('Ungüento'),
('Aerosol');

-- Presentaciones
INSERT INTO presentacion (descripcion) VALUES 
('Caja x 10'),
('Caja x 20'),
('Caja x 30'),
('Frasco x 100ml'),
('Frasco x 250ml'),
('Ampolla x 1ml'),
('Ampolla x 5ml');

-- ============================================
-- VISTAS ÚTILES
-- ============================================

-- Vista de solicitudes completa
CREATE VIEW vista_solicitudes AS
SELECT 
    s.id_solicitud,
    s.fecha,
    s.hora,
    CONCAT(p.apellido, ', ', p.nombre) AS personal,
    CONCAT(ps.apellido, ', ', ps.nombre) AS profesional_solicitante,
    a.descripcion AS area,
    e.descripcion AS estado,
    ds.cantidad,
    m.nombre AS material
FROM solicitud s
JOIN personal p ON s.id_personal = p.dni_personal
JOIN profesional_solicitante ps ON s.id_profesional_solicitante = ps.dni_profesional_solicitante
JOIN area a ON s.id_area = a.id_area
JOIN estado e ON s.id_estado = e.id_estado
LEFT JOIN detalle_solicitud ds ON s.id_detalle_solicitud = ds.id_detalle_solicitud
LEFT JOIN material m ON ds.id_material = m.id_material;

-- Vista de stock de materiales
CREATE VIEW vista_stock AS
SELECT 
    m.id_material,
    m.nombre,
    tm.descripcion AS tipo,
    ff.descripcion AS forma_farmaceutica,
    pr.descripcion AS presentacion,
    m.fecha_vencimiento,
    p.razon_social AS proveedor,
    DATEDIFF(m.fecha_vencimiento, CURDATE()) AS dias_hasta_vencimiento
FROM material m
JOIN tipo_material tm ON m.id_tipo_material = tm.id_tipo_material
LEFT JOIN forma_farmaceutica ff ON m.id_forma_farmaceutica = ff.id_forma_farmaceutica
LEFT JOIN presentacion pr ON m.id_presentacion = pr.id_presentacion
JOIN proveedor p ON m.id_proveedor = p.id_proveedor;

-- ============================================
-- FIN DEL SCRIPT
-- ============================================