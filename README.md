# ARQUITECTURA ATM PARA LA GESTIÓN DEL SERVICIO AL CLIENTE - BANCO FIE

Este proyecto integrador desarrolla un sistema de Cajero Automático (ATM) enfocado en la inclusión financiera y la automatización de transacciones para el Banco FIE en Bolivia. El sistema busca reducir la saturación en ventanillas y ofrecer servicios seguros las 24 horas, especialmente en zonas con conectividad limitada.

## 👥 Equipo de desarrollo
* **Juan Carlos Chavez Machaca**
* **Kevin Jiménez Jheferson Quisbert**
* **Erick Ivan Luna Tarqui**
* **Fernando Castro Vargas**
* **Juan Antonio Ramos Rojas**

## 🎯 Objetivo del Proyecto
Desarrollar un sistema de cajero automático (ATM) para gestionar de forma eficiente y segura las transacciones bancarias del Banco FIE, empleando hardware y software con especial énfasis en la usabilidad y la inclusión financiera.

## 🛠️ Herramientas y Stack Tecnológico

### **Presentación (Frontend)**
* **HTML5 & CSS3:** Diseño de interfaces bilingües (Español/Inglés).
* **jQuery:** Implementación de una **Single Page Application (SPA)** para transiciones fluidas.
* **UX Inclusiva:** Diseño orientado a usuarios con baja experiencia digital.

### **Backend (Lógica de Negocio)**
* **PHP:** Procesamiento de la lógica bancaria bajo el patrón **MVC**.
* **API RESTful:** Endpoints seguros con formato JSON y manejo de códigos HTTP.
* **Seguridad Multicapa:**
    * Autenticación mediante **JWT** con expiración de sesión.
    * Hashing de PINs con `password_hash()` y salting.
    * Protección contra inyección SQL mediante **PDO** con consultas preparadas.
    * Encriptación de datos sensibles con **AES-256-CBC**.

### **Datos y Hardware**
* **MySQL:** Base de datos para el registro seguro de transacciones y logs de auditoría.
* **Arduino UNO (IoT):** Integración de hardware mediante microcontrolador para el control físico.
* **Periféricos:** Lector RFID EM-18, sensor biométrico GT511 y módulo GSM 900.

## 📂 Estructura del Sistema
```text
APP/
├── api/             # Endpoints RESTful y lógica de servidor
├── arduino/         # Código fuente para microcontrolador Arduino
├── config/          # Archivos de configuración del sistema
├── database/        # Scripts SQL y modelos de datos
└── public/          # Archivos accesibles desde el navegador
    ├── assets/      # Recursos estáticos
    │   ├── css/     # Hojas de estilo
    │   └── lang/    # Archivos JSON para soporte bilingüe
    ├── consulta.html
    ├── cuenta.html
    ├── deposito.html
    ├── index.html   # Punto de entrada principal
    ├── language.html
    ├── menu.html
    ├── movimientos.html
    ├── pin.html
    ├── resultado.html
    └── retiro.html
