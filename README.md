# FLUJO

Sistema operativo de desarrollo personal para Claude Code. Una colección de skills, metodología y estructura lista para usar en cualquier proyecto — sin configurar nada desde cero.

---

## ¿Qué es FLUJO?

FLUJO unifica tres cosas en un solo repositorio:

1. **Sistema operativo personal** — skills + workflow predefinido listo para cualquier proyecto
2. **Plantilla reutilizable** — clónalo al iniciar un proyecto nuevo y ya tienes todo
3. **Laboratorio de experimentación** — el espacio donde se prueban y refinan metodologías de trabajo con IA

---

## Skills incluidas (18)

### Proceso — Superpowers
| Skill | Propósito |
|---|---|
| `brainstorming` | Explorar ideas antes de diseñar cualquier feature |
| `writing-plans` | Generar planes de implementación con tareas de 2-5 min |
| `executing-plans` | Ejecutar planes con checkpoints de revisión |
| `subagent-driven-development` | Coordinar múltiples subagentes en paralelo |
| `dispatching-parallel-agents` | Despachar tareas independientes en paralelo |
| `test-driven-development` | TDD estricto: test → falla → código → pasa → commit |
| `systematic-debugging` | Debuggear con evidencia, no suposiciones |
| `requesting-code-review` | Solicitar code review estructurado |
| `receiving-code-review` | Procesar feedback con rigor técnico |
| `verification-before-completion` | Verificar antes de declarar que algo funciona |
| `finishing-a-development-branch` | Cerrar ramas con opciones de merge/PR |
| `using-git-worktrees` | Aislar trabajo en worktrees independientes |
| `using-superpowers` | Guía de uso del ecosistema Superpowers |
| `writing-skills` | Crear y editar skills nuevas |

### Diseño
| Skill | Propósito |
|---|---|
| `ui-ux-pro-max` | Sistema de diseño: paletas, tipografía, estilos, reglas UX |
| `impeccable` | Auditoría, critique, polish y hardening de UI |
| `emil-design-eng` | Animaciones y micro-interacciones con propósito |

### Conocimiento
| Skill | Propósito |
|---|---|
| `graphify` | Mapear proyectos a grafos de conocimiento consultables |

---

## Requisitos

- **Git** — para clonar y gestionar ramas
- **Node.js + npm** — para Impeccable (`npx impeccable skills install`)
- **Python 3.10+** — para Graphify (`pip install graphifyy`)
- **Claude Code** — donde corren todas las skills

---

## Cómo usar FLUJO en un proyecto nuevo

### Opción A — Clonar como base

```powershell
git clone https://github.com/MaykMenchaca/FLUJO.git nombre-proyecto
cd nombre-proyecto
Remove-Item -Recurse -Force .git
git init
git remote add origin <url-del-nuevo-repo>
```

### Opción B — Copiar skills a un proyecto existente

```powershell
# Desde la raíz de tu proyecto existente
powershell -File "C:\ruta\a\FLUJO\setup.ps1"
```

Después de cualquiera de las dos opciones, abre el proyecto en Claude Code y corre:

```
/impeccable init
```

Para generar `PRODUCT.md` y `DESIGN.md` con la identidad del nuevo proyecto.

---

## Estructura del repositorio

```
FLUJO/
│
├── CLAUDE.md                  ← Metodología multi-agente completa (leer primero)
├── PRODUCT.md                 ← Identidad de FLUJO como producto
├── requirements.txt           ← Dependencias Python (graphifyy)
├── setup.ps1                  ← Script de replicación a otros proyectos
│
├── .claude/
│   └── skills/                ← Las 18 skills listas para usar
│       ├── brainstorming/
│       ├── impeccable/
│       ├── ui-ux-pro-max/
│       ├── emil-design-eng/
│       ├── graphify/
│       └── ... (13 más)
│
├── design-system/
│   ├── MASTER.md              ← Sistema de diseño técnico (se genera por proyecto)
│   └── pages/                 ← Overrides visuales por sección
│
└── docs/
    ├── project-context.md     ← Memoria viva del proyecto (se llena por proyecto)
    └── superpowers/
        ├── specs/             ← Specs aprobadas por el usuario
        └── plans/             ← Planes de implementación generados
```

---

## Metodología

FLUJO sigue una arquitectura de 4 niveles de agentes:

```
NIVEL 0 — Orquestador Principal
├── NIVEL 1 — Planeación   (brainstorming → writing-plans)
├── NIVEL 2 — Diseño       (ui-ux-pro-max → impeccable → emil-design-eng)
└── NIVEL 3 — Código       (subagent-driven-development + TDD)
```

Cada nivel tiene un flujo obligatorio documentado en `CLAUDE.md`.

---

## Dónde entra Graphify

| Momento | Comando |
|---|---|
| Inicio de sesión en proyecto existente | `/graphify .` |
| Arquitecto define estructura (Nivel 3) | `/graphify . --update` |
| Debugging entre módulos | `/graphify query "pregunta"` o `/graphify path "A" "B"` |

---

## Filosofía

- **YAGNI** — No construyas lo que no se pidió
- **TDD siempre** — Los tests van antes que el código
- **Evidencia sobre suposiciones** — Verifica antes de declarar que algo funciona
- **El flujo reemplaza la fricción** — La metodología elimina decisiones repetitivas
- **Visible y comprensible** — Sin cajas negras, sin magia opaca
