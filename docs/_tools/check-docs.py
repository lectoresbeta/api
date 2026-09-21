#!/usr/bin/env python3
"""Valida la coherencia de la documentación de Lectores Beta.

Comprobaciones:
  1. El front matter de cada ficha de funcionalidad es válido y tiene los campos obligatorios.
  2. Los identificadores FEAT- son únicos y coinciden con el nombre del fichero.
  3. Los valores de spec_status e impl_status son los definidos en conventions.md.
  4. Toda ficha aparece en el registro maestro con los mismos estados.
  5. Toda fila del registro que enlaza una ficha apunta a un fichero existente.
  6. Los enlaces relativos entre documentos markdown resuelven.

Uso:  python3 docs/_tools/check-docs.py
Salida: 0 si todo es coherente, 1 si hay errores.
"""

import re
import sys
from pathlib import Path

DOCS = Path(__file__).resolve().parent.parent
REGISTRY = DOCS / "features" / "README.md"

REQUIRED_FIELDS = ["id", "title", "context", "spec_status", "impl_status", "priority", "updated"]
SPEC_STATUSES = {"PENDING", "DRAFT", "REVIEW", "APPROVED"}
IMPL_STATUSES = {"TODO", "IN_PROGRESS", "PARTIAL", "DONE", "BLOCKED", "DEFERRED", "DEPRECATED"}
FEATURE_ID = re.compile(r"^FEAT-[A-Z]{3}-\d{3}$")

errors: list[str] = []
warnings: list[str] = []


def rel(path: Path) -> str:
    return str(path.relative_to(DOCS.parent))


def parse_front_matter(text: str) -> dict[str, str] | None:
    """Extrae el front matter como pares clave/valor. Sin dependencias externas."""
    if not text.startswith("---"):
        return None
    end = text.find("\n---", 3)
    if end == -1:
        return None
    data: dict[str, str] = {}
    for line in text[3:end].splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or stripped.startswith("- "):
            continue
        if ":" not in line or line.startswith(" "):
            continue
        key, _, value = line.partition(":")
        data[key.strip()] = value.strip()
    return data


def check_features() -> dict[str, dict[str, str]]:
    found: dict[str, dict[str, str]] = {}
    for path in sorted((DOCS / "features").rglob("FEAT-*.md")):
        text = path.read_text(encoding="utf-8")
        front = parse_front_matter(text)
        if front is None:
            errors.append(f"{rel(path)}: falta el front matter YAML")
            continue

        for field in REQUIRED_FIELDS:
            if field not in front:
                errors.append(f"{rel(path)}: falta el campo obligatorio '{field}'")

        feature_id = front.get("id", "")
        if not FEATURE_ID.match(feature_id):
            errors.append(f"{rel(path)}: id '{feature_id}' no sigue el formato FEAT-CTX-NNN")
            continue

        if not path.name.startswith(feature_id):
            errors.append(f"{rel(path)}: el nombre del fichero no empieza por su id '{feature_id}'")

        if feature_id in found:
            errors.append(f"{rel(path)}: id duplicado '{feature_id}'")
        found[feature_id] = front

        spec = front.get("spec_status", "")
        if spec not in SPEC_STATUSES:
            errors.append(f"{rel(path)}: spec_status '{spec}' no es válido")
        impl = front.get("impl_status", "")
        if impl not in IMPL_STATUSES:
            errors.append(f"{rel(path)}: impl_status '{impl}' no es válido")

        if impl == "PARTIAL" and "falta" not in text.lower():
            warnings.append(f"{rel(path)}: impl_status PARTIAL debe explicar qué falta")
        if impl == "BLOCKED" and "bloque" not in text.lower():
            warnings.append(f"{rel(path)}: impl_status BLOCKED debe explicar qué lo bloquea")
    return found


def check_registry(features: dict[str, dict[str, str]]) -> None:
    if not REGISTRY.exists():
        errors.append(f"{rel(REGISTRY)}: no existe el registro maestro")
        return

    text = REGISTRY.read_text(encoding="utf-8")
    rows: dict[str, tuple[str, str, str]] = {}
    in_feature_table = False
    for line in text.splitlines():
        if line.startswith("| ID | Funcionalidad |"):
            in_feature_table = True
            continue
        if not line.startswith("|"):
            in_feature_table = False
            continue
        if not in_feature_table or not line.startswith("| FEAT-"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) != 7:
            errors.append(f"registro: fila mal formada -> {line[:60]}")
            continue
        rows[cells[0]] = (cells[3], cells[4], cells[6])

    for feature_id, (spec, impl, link) in rows.items():
        if spec not in SPEC_STATUSES:
            errors.append(f"registro {feature_id}: spec '{spec}' no es válido")
        if impl not in IMPL_STATUSES:
            errors.append(f"registro {feature_id}: impl '{impl}' no es válido")

        match = re.search(r"\]\(([^)]+)\)", link)
        if match:
            target = (DOCS / "features" / match.group(1)).resolve()
            if not target.exists():
                errors.append(f"registro {feature_id}: la ficha enlazada no existe ({match.group(1)})")
        elif feature_id in features:
            errors.append(f"registro {feature_id}: existe la ficha pero la fila no la enlaza")

    for feature_id, front in features.items():
        if feature_id not in rows:
            errors.append(f"{feature_id}: tiene ficha pero no aparece en el registro maestro")
            continue
        spec, impl, _ = rows[feature_id]
        if front.get("spec_status") != spec:
            errors.append(
                f"{feature_id}: spec_status difiere -> ficha '{front.get('spec_status')}' vs registro '{spec}'"
            )
        if front.get("impl_status") != impl:
            errors.append(
                f"{feature_id}: impl_status difiere -> ficha '{front.get('impl_status')}' vs registro '{impl}'"
            )


def check_links() -> None:
    pattern = re.compile(r"\[[^\]]*\]\(([^)]+)\)")
    for path in sorted(DOCS.rglob("*.md")):
        for line_no, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
            for target in pattern.findall(line):
                if target.startswith(("http://", "https://", "#", "mailto:")):
                    continue
                resolved = (path.parent / target.split("#", 1)[0]).resolve()
                if not resolved.exists():
                    errors.append(f"{rel(path)}:{line_no}: enlace roto -> {target}")


def main() -> int:
    features = check_features()
    check_registry(features)
    check_links()

    for warning in warnings:
        print(f"AVISO  {warning}")
    for error in errors:
        print(f"ERROR  {error}")

    print(f"\nFichas de funcionalidad encontradas: {len(features)}")
    if errors:
        print(f"Errores: {len(errors)}")
        return 1
    print("Documentación coherente.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
