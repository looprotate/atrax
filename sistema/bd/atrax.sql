DROP DATABASE IF EXISTS atrax;
CREATE DATABASE atrax CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atrax;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(20) NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE zonas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(5) NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  barrios VARCHAR(150) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_zonas_codigo (codigo),
  UNIQUE KEY uq_zonas_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rol_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  usuario VARCHAR(40) NOT NULL,
  email VARCHAR(150) NOT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  contrasena VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login DATETIME DEFAULT NULL,
  UNIQUE KEY uq_usuarios_usuario (usuario),
  UNIQUE KEY uq_usuarios_email (email),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  zona_id INT NOT NULL,
  tipo ENUM('Particular','Empresa','Institucion') NOT NULL DEFAULT 'Particular',
  direccion VARCHAR(200) NOT NULL,
  UNIQUE KEY uq_clientes_usuario (usuario_id),
  CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_clientes_zona FOREIGN KEY (zona_id) REFERENCES zonas (id)
) ENGINE=InnoDB;

CREATE TABLE vehiculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zona_id INT NOT NULL,
  nombre VARCHAR(40) NOT NULL,
  matricula VARCHAR(15) NOT NULL,
  capacidad INT NOT NULL,
  estado ENUM('Disponible','En ruta','Mantenimiento') NOT NULL DEFAULT 'Disponible',
  UNIQUE KEY uq_vehiculos_matricula (matricula),
  CONSTRAINT fk_vehiculos_zona FOREIGN KEY (zona_id) REFERENCES zonas (id),
  CONSTRAINT ck_vehiculos_capacidad CHECK (capacidad > 0)
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  stock_minimo INT NOT NULL DEFAULT 0,
  imagen VARCHAR(120) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_productos_nombre (nombre),
  CONSTRAINT ck_productos_precio CHECK (precio > 0),
  CONSTRAINT ck_productos_minimo CHECK (stock_minimo >= 0)
) ENGINE=InnoDB;

CREATE TABLE planes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  viandas INT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_planes_nombre (nombre),
  CONSTRAINT ck_planes_viandas CHECK (viandas > 0)
) ENGINE=InnoDB;

CREATE TABLE lotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero_lote VARCHAR(20) NOT NULL,
  producto_id INT NOT NULL,
  operador_id INT NOT NULL,
  fecha_produccion DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  cantidad INT NOT NULL,
  disponible INT NOT NULL,
  estado ENUM('Disponible','Agotado','Descartado') NOT NULL DEFAULT 'Disponible',
  UNIQUE KEY uq_lotes_numero (numero_lote),
  CONSTRAINT fk_lotes_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_lotes_operador FOREIGN KEY (operador_id) REFERENCES usuarios (id),
  CONSTRAINT ck_lotes_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_lotes_disponible CHECK (disponible >= 0 AND disponible <= cantidad)
) ENGINE=InnoDB;

CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  plan_id INT NOT NULL,
  zona_id INT NOT NULL,
  vehiculo_id INT DEFAULT NULL,
  fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_entrega DATE NOT NULL,
  estado ENUM('Pendiente','Preparando','Listo','En distribucion','Entregado','Cancelado') NOT NULL DEFAULT 'Pendiente',
  viandas INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_pedidos_plan FOREIGN KEY (plan_id) REFERENCES planes (id),
  CONSTRAINT fk_pedidos_zona FOREIGN KEY (zona_id) REFERENCES zonas (id),
  CONSTRAINT fk_pedidos_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos (id),
  CONSTRAINT ck_pedidos_total CHECK (total >= 0)
) ENGINE=InnoDB;

CREATE TABLE pedido_detalle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uq_detalle_pedido_producto (pedido_id, producto_id),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE TABLE pedido_estados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  estado_anterior VARCHAR(20) DEFAULT NULL,
  estado_nuevo VARCHAR(20) NOT NULL,
  usuario_id INT NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_estados_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
  CONSTRAINT fk_estados_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB;

CREATE TABLE movimientos_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lote_id INT NOT NULL,
  usuario_id INT NOT NULL,
  pedido_id INT DEFAULT NULL,
  tipo ENUM('Entrada','Salida','Ajuste') NOT NULL,
  cantidad INT NOT NULL,
  motivo VARCHAR(150) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movimientos_lote FOREIGN KEY (lote_id) REFERENCES lotes (id),
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
  CONSTRAINT fk_movimientos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE SET NULL,
  CONSTRAINT ck_movimientos_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE TABLE intentos_acceso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identificador VARCHAR(150) NOT NULL,
  direccion VARCHAR(45) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_intentos_identificador (identificador, fecha),
  KEY idx_intentos_direccion (direccion, fecha)
) ENGINE=InnoDB;

INSERT INTO roles (id, nombre, descripcion) VALUES
(1, 'administrador', 'Gerente: parametriza el sistema, gestiona menus y consulta estadisticas'),
(2, 'operador', 'Jefe de cocina: produccion, stock FIFO y distribucion'),
(3, 'cliente', 'Particular, empresa o institucion que realiza pedidos');

INSERT INTO zonas (id, codigo, nombre, barrios, activa) VALUES
(1, 'Z1', 'Centro', 'Centro, Cordon', 1),
(2, 'Z2', 'Pocitos', 'Pocitos, Punta Carretas', 1),
(3, 'Z3', 'Carrasco', 'Carrasco, Malvin', 1),
(4, 'Z4', 'Prado', 'Prado, Capurro', 1),
(5, 'Z5', 'Cerro', 'Cerro, La Teja', 1),
(6, 'Z6', 'Union', 'Union, Maronas', 1),
(7, 'Z7', 'Sayago', 'Sayago, Colon', 1);

INSERT INTO usuarios (id, rol_id, nombre, usuario, email, telefono, contrasena, activo, fecha_registro) VALUES
(1, 1, 'Kiara Acosta', 'admin', 'admin@atrax.uy', '+598 99 111 111', '$2y$10$Wvl5rPIgwFUb/uCo9fkNCeS7NCUppiWqSwcwcNUOa/xOJRuS/q3da', 1, DATE_SUB(NOW(), INTERVAL 120 DAY)),
(2, 1, 'Pierina Lopez', 'gerente', 'gerente@atrax.uy', '+598 99 111 222', '$2y$10$QJqBE6UtjB9IGyCZ8zpjWO.B9G08F2tm7JiIDd8gj8PlqxY20nKVm', 1, DATE_SUB(NOW(), INTERVAL 120 DAY)),
(3, 2, 'Alma Nunes de Moraes', 'cocina', 'cocina@atrax.uy', '+598 99 222 111', '$2y$10$kIS18YDg1OrM4YxFcGdI2Okwl5MmML3BGbbWm05jk6MZ2nZB7c2ta', 1, DATE_SUB(NOW(), INTERVAL 110 DAY)),
(4, 2, 'Kevin Arregin', 'cocina2', 'cocina2@atrax.uy', '+598 99 222 222', '$2y$10$RMNlHz/aNxNEMcNgIn3eReKMd2mKdIEet4GVhstqXJjnzINHsyfT2', 1, DATE_SUB(NOW(), INTERVAL 110 DAY)),
(5, 3, 'Julio Rodriguez', 'julio', 'julio@correo.com', '+598 99 123 456', '$2y$10$..kMVV85F5AzwUYqirDLI.Y2wOItMUKbWG9VOvcUkr9.cK.gDp8cG', 1, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(6, 3, 'Estudio Contable Sur', 'estudiosur', 'contacto@estudiosur.uy', '+598 2 400 5060', '$2y$10$o9s2jlIFCLkcnebXRkSxbOXGpTA0kIpMzNTjeWBtoJ6kpQctf0CQO', 1, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(7, 3, 'Lucia Fernandez', 'lucia', 'lucia@correo.com', '+598 99 654 321', '$2y$10$T0YBGYXBUs1e00ZBYAbuhOMdFOgJCQAEqcms7UkyIQh.nTdiCqk2K', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 3, 'Liceo Impulso', 'liceoimpulso', 'secretaria@impulso.edu.uy', '+598 2 411 2233', '$2y$10$I/GKn/40bvpIdQl7hGNzNu.lOU5mb.yvFUvhUZ36viSqjRBwjk7ga', 1, DATE_SUB(NOW(), INTERVAL 15 DAY));

INSERT INTO clientes (id, usuario_id, zona_id, tipo, direccion) VALUES
(1, 5, 2, 'Particular', 'Av. Brasil 2550 apto 401'),
(2, 6, 1, 'Empresa', 'Av. 18 de Julio 1250 piso 3'),
(3, 7, 3, 'Particular', 'Av. Bolivia 1420'),
(4, 8, 6, 'Institucion', 'Camino Maldonado 3100');

INSERT INTO vehiculos (id, zona_id, nombre, matricula, capacidad, estado) VALUES
(1, 1, 'Furgon 1', 'ABC 1234', 60, 'Disponible'),
(2, 2, 'Furgon 2', 'ABC 5678', 60, 'En ruta'),
(3, 3, 'Moto 1', 'XY 9012', 18, 'Disponible'),
(4, 6, 'Moto 2', 'XY 3456', 18, 'Mantenimiento');

INSERT INTO productos (id, nombre, descripcion, precio, stock_minimo, imagen, activo) VALUES
(1, 'Milanesa con ensalada', 'Milanesa de carne al horno con ensalada mixta', 490.00, 15, 'milanesa-con-ensalada.jpg', 1),
(2, 'Pollo grillado con pure', 'Suprema grillada con pure de papa y calabaza', 490.00, 15, 'pollo-grillado-con-pure.jpg', 1),
(3, 'Tarta de verdura', 'Tarta de acelga y ricota con masa casera', 460.00, 10, 'tarta-de-verdura.jpg', 1),
(4, 'Ensalada de quinoa', 'Quinoa con vegetales de estacion y semillas', 470.00, 12, 'ensalada-de-quinoa.jpg', 1),
(5, 'Wok de vegetales', 'Vegetales salteados con arroz integral', 470.00, 15, 'wok-de-vegetales.jpg', 1);

INSERT INTO planes (id, nombre, descripcion, viandas, activo) VALUES
(1, 'Semanal', '5 viandas, de lunes a viernes', 5, 1),
(2, 'Quincenal', '10 viandas, dos semanas', 10, 1),
(3, 'Mensual', '20 viandas, un mes completo', 20, 1);

INSERT INTO lotes (id, numero_lote, producto_id, operador_id, fecha_produccion, fecha_vencimiento, cantidad, disponible, estado) VALUES
(1, 'L-0980', 1, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), 6, 0, 'Agotado'),
(2, 'L-0981', 2, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), 5, 0, 'Agotado'),
(3, 'L-0982', 3, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 3, 0, 'Agotado'),
(4, 'L-0983', 5, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE(), 8, 0, 'Agotado'),
(5, 'L-0984', 4, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE(), 3, 0, 'Agotado'),
(6, 'L-0991', 3, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), CURDATE(), 11, 11, 'Disponible'),
(7, 'L-0994', 1, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 30, 25, 'Disponible'),
(8, 'L-0996', 2, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 8, 8, 'Disponible'),
(9, 'L-0998', 4, 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 5, 5, 'Disponible'),
(10, 'L-0999', 5, 4, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 30, 30, 'Disponible');

INSERT INTO pedidos (id, cliente_id, plan_id, zona_id, vehiculo_id, fecha_pedido, fecha_entrega, estado, viandas, total) VALUES
(1, 1, 1, 2, 2, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Entregado', 5, 2450.00),
(2, 2, 2, 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), CURDATE(), 'En distribucion', 10, 4750.00),
(3, 3, 1, 3, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), CURDATE(), 'Listo', 5, 2390.00),
(4, 4, 1, 6, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Pendiente', 5, 2350.00),
(5, 1, 1, 2, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), CURDATE(), 'Pendiente', 5, 2450.00);

INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 2, 490.00, 980.00),
(1, 2, 3, 490.00, 1470.00),
(2, 1, 4, 490.00, 1960.00),
(2, 3, 3, 460.00, 1380.00),
(2, 5, 3, 470.00, 1410.00),
(3, 2, 2, 490.00, 980.00),
(3, 4, 3, 470.00, 1410.00),
(4, 5, 5, 470.00, 2350.00),
(5, 1, 5, 490.00, 2450.00);

INSERT INTO pedido_estados (pedido_id, estado_anterior, estado_nuevo, usuario_id, fecha) VALUES
(1, NULL, 'Pendiente', 5, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 'Pendiente', 'Preparando', 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Preparando', 'Listo', 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Listo', 'En distribucion', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'En distribucion', 'Entregado', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, NULL, 'Pendiente', 6, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'Pendiente', 'Preparando', 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'Preparando', 'Listo', 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'Listo', 'En distribucion', 4, NOW()),
(3, NULL, 'Pendiente', 7, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'Pendiente', 'Preparando', 4, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'Preparando', 'Listo', 4, NOW()),
(4, NULL, 'Pendiente', 8, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, NULL, 'Pendiente', 5, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO movimientos_stock (lote_id, usuario_id, pedido_id, tipo, cantidad, motivo, fecha) VALUES
(1, 3, NULL, 'Entrada', 6, 'Produccion lote L-0980', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 3, NULL, 'Entrada', 5, 'Produccion lote L-0981', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(3, 4, NULL, 'Entrada', 3, 'Produccion lote L-0982', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 4, NULL, 'Entrada', 8, 'Produccion lote L-0983', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 3, NULL, 'Entrada', 3, 'Produccion lote L-0984', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 3, NULL, 'Entrada', 11, 'Produccion lote L-0991', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 3, NULL, 'Entrada', 30, 'Produccion lote L-0994', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8, 4, NULL, 'Entrada', 8, 'Produccion lote L-0996', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 3, NULL, 'Entrada', 5, 'Produccion lote L-0998', NOW()),
(10, 4, NULL, 'Entrada', 30, 'Produccion lote L-0999', NOW()),
(1, 5, 1, 'Salida', 2, 'Pedido #1', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 5, 1, 'Salida', 3, 'Pedido #1', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 6, 2, 'Salida', 4, 'Pedido #2', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, 2, 'Salida', 3, 'Pedido #2', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 6, 2, 'Salida', 3, 'Pedido #2', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 7, 3, 'Salida', 2, 'Pedido #3', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 7, 3, 'Salida', 3, 'Pedido #3', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 8, 4, 'Salida', 5, 'Pedido #4', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 5, 5, 'Salida', 5, 'Pedido #5', DATE_SUB(NOW(), INTERVAL 1 DAY));
