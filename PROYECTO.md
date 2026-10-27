# REDES SOCIALES MANAGER

Plataforma comercial de gestión de contenidos, publicación multicanal e inteligencia comercial para clientes. Este archivo es el punto de entrada de la adaptación de Shoutrrr.

## Base y estado

Base inspeccionada: `28bbc7f9e1ff225ffda264185e45d756a46b82b4`, rama `main`, 26 de octubre de 2026. Repositorio: https://github.com/lacibelestudio-sudo/REDES-SOCIALES-MANAGER

Esta entrega establece el contexto técnico y el plan de implementación. No incorpora todavía nuevas pantallas, conectores ni migraciones. El funcionamiento en Windows fue reportado por el propietario; esta revisión es de código y no equivale a una prueba de publicación real.

## Lectura del proyecto

1. [Auditoría de la base](AUDITORIA_BASE.md): funciones encontradas y brechas.
2. [Decisiones de arquitectura](ARQUITECTURA_PRODUCTO.md): stack, aislamiento e integraciones.
3. [Contrato del dashboard](CONTRATO_BI.md): significado y calidad de los indicadores.
4. [Plan de implementación](PLAN_IMPLEMENTACION.md): entregas y pruebas de aceptación.
5. [Continuación desde VS Code](CONTINUAR_VSCODE.md): siguiente tarea concreta.

Las instrucciones técnicas siguen en [AGENTS.md](AGENTS.md) y [CLAUDE.md](CLAUDE.md). La documentación original permanece en [README.md](README.md). Conservar [LICENSE](LICENSE) y las atribuciones del proyecto base.

## Objetivos

- Crear, revisar, programar y publicar contenido orgánico, priorizando Facebook, Instagram y LinkedIn.
- Gestionar clientes con aislamiento de datos, miembros y permisos.
- Entregar gráficos y reportes que conecten resultados de redes con leads, oportunidades y ventas cuando exista información de CRM.
- Evaluar el uso de Upload-Post o Ayrshare únicamente para brechas demostradas de los conectores actuales.
- Incorporar generación asistida por IA y revisión humana en una fase explícita.

La gestión de anuncios constituye un módulo separado. No se presupone que una API de publicación permita comprar anuncios ni que elimine los permisos exigidos por cada red.

## Alcance inicial

Primero: calidad de métricas, validación de conexiones reales, dashboard filtrable y reportes por cliente. Después: contenido asistido, aprobación, campañas/UTM y CRM. Power BI Embedded y compra de anuncios quedan como ampliaciones evaluables.

Las propuestas anteriores basadas en FastAPI o siete roles nuevos quedan sustituidas, para implementación, por las decisiones de este paquete. No importar la documentación anterior como si describiera funciones existentes.
