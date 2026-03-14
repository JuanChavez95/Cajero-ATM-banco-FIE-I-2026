-- =============================================
-- ATM DB — Script completo reestructurado
-- Versión 2.0
-- =============================================

DROP DATABASE IF EXISTS atm_db;

CREATE DATABASE atm_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE atm_db;

-- ==============================================
-- TABLA: usuarios
-- Datos personales y autenticación
-- Un usuario puede tener varias cuentas
-- ==============================================
CREATE TABLE usuarios (
    id                   INT AUTO_INCREMENT PRIMARY KEY,

    nombre               VARCHAR(100) NOT NULL,
    apellido             VARCHAR(100) NOT NULL,
    ci                   VARCHAR(20)  NOT NULL UNIQUE,       -- Cédula de identidad
    telefono             VARCHAR(20),
    email                VARCHAR(150),

    numero_tarjeta       CHAR(16)     NOT NULL UNIQUE,
    pin_hash             VARCHAR(255) NOT NULL,              -- bcrypt

    idioma_preferido     ENUM('es','en') NOT NULL DEFAULT 'es',

    estado               ENUM('activo','bloqueado') NOT NULL DEFAULT 'activo',
    intentos_fallidos    INT NOT NULL DEFAULT 0,

    fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tarjeta (numero_tarjeta),
    INDEX idx_ci      (ci)
) ENGINE=InnoDB;


-- ==============================================
-- TABLA: cuentas
-- Un usuario puede tener múltiples cuentas
-- (ahorros, corriente, etc.)
-- ==============================================
CREATE TABLE cuentas (
    id               INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id       INT          NOT NULL,
    numero_cuenta    CHAR(12)     NOT NULL UNIQUE,           -- Ej: 000100000001
    tipo             ENUM('ahorros','corriente') NOT NULL DEFAULT 'ahorros',
    saldo            DECIMAL(12,2) NOT NULL DEFAULT 0.00 CHECK (saldo >= 0),

    estado           ENUM('activa','bloqueada','cerrada') NOT NULL DEFAULT 'activa',

    fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    INDEX idx_usuario  (usuario_id),
    INDEX idx_numero   (numero_cuenta)
) ENGINE=InnoDB;


-- ==============================================
-- TABLA: movimientos
-- Registra cada transacción ligada a una cuenta
-- cuenta_destino_id se usa solo en transferencias
-- ==============================================
CREATE TABLE movimientos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,

    cuenta_id           INT NOT NULL,                        -- Cuenta origen
    cuenta_destino_id   INT DEFAULT NULL,                    -- Solo para transferencias

    tipo                ENUM('retiro','deposito','transferencia') NOT NULL,
    monto               DECIMAL(12,2) NOT NULL CHECK (monto > 0),

    saldo_anterior      DECIMAL(12,2) NOT NULL,
    saldo_posterior     DECIMAL(12,2) NOT NULL,

    descripcion         VARCHAR(255),
    fecha               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (cuenta_id)
        REFERENCES cuentas(id)
        ON DELETE CASCADE,

    FOREIGN KEY (cuenta_destino_id)
        REFERENCES cuentas(id)
        ON DELETE SET NULL,

    INDEX idx_cuenta (cuenta_id),
    INDEX idx_fecha  (fecha)
) ENGINE=InnoDB;


-- ==============================================
-- TABLA: comprobantes
-- Se genera solo si el usuario lo solicita
-- ==============================================
CREATE TABLE comprobantes (
    id             INT AUTO_INCREMENT PRIMARY KEY,

    movimiento_id  INT NOT NULL UNIQUE,
    solicitado     TINYINT(1) NOT NULL DEFAULT 0,            -- 1 = sí quiso comprobante
    generado       TINYINT(1) NOT NULL DEFAULT 0,            -- 1 = se generó correctamente
    fecha          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (movimiento_id)
        REFERENCES movimientos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ==============================================
-- DATOS DE PRUEBA
-- ==============================================

-- Usuario 1: Juan Pérez — tiene cuenta de ahorros y corriente
INSERT INTO usuarios (nombre, apellido, ci, telefono, email, numero_tarjeta, pin_hash, idioma_preferido)
VALUES (
    'Juan', 'Pérez',
    '12345678',
    '77712345',
    'juan.perez@email.com',
    '1234567890123456',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- PIN: 1234
    'es'
);

-- Usuario 2: Ana Gómez — solo cuenta de ahorros
INSERT INTO usuarios (nombre, apellido, ci, telefono, email, numero_tarjeta, pin_hash, idioma_preferido)
VALUES (
    'Ana', 'Gómez',
    '87654321',
    '77798765',
    'ana.gomez@email.com',
    '6543210987654321',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- PIN: 1234
    'en'
);

-- Cuentas de Juan (usuario_id = 1)
INSERT INTO cuentas (usuario_id, numero_cuenta, tipo, saldo)
VALUES
    (1, '000100000001', 'ahorros',   5000.00),
    (1, '000100000002', 'corriente', 1200.50);

-- Cuenta de Ana (usuario_id = 2)
INSERT INTO cuentas (usuario_id, numero_cuenta, tipo, saldo)
VALUES
    (2, '000200000001', 'ahorros', 3400.00);

-- Movimientos de prueba para Juan — cuenta ahorros (cuenta id=1)
INSERT INTO movimientos (cuenta_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
VALUES
    (1, 'deposito',  1000.00, 4000.00, 5000.00, 'Depósito en cajero'),
    (1, 'retiro',     200.00, 5000.00, 4800.00, 'Retiro en cajero');

-- Transferencia de Juan ahorros → Juan corriente
INSERT INTO movimientos (cuenta_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
VALUES
    (1, 2, 'transferencia', 300.00, 4800.00, 4500.00, 'Transferencia a cuenta corriente');