#!/bin/bash
#
# rename_to_class_php.sh
#
# Renomeia arquivos *.php para *.class.php dentro de uma pasta
# (e subpastas), pulando os que já têm .class.php.
#
# USO:
#   ./rename_to_class_php.sh /caminho/para/app/control
#   ./rename_to_class_php.sh /caminho/para/app/control --dry-run
#
# OPÇÕES:
#   --dry-run   Só mostra o que faria, não renomeia nada
#

# ---------- Parse args ----------
DIR=""
DRY_RUN=0

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        -h|--help)
            echo "Uso: $0 /caminho/para/pasta [--dry-run]"
            exit 0
            ;;
        *) DIR="$arg" ;;
    esac
done

# ---------- Validações ----------
if [ -z "$DIR" ]; then
    echo "❌ Informe a pasta. Ex: $0 /var/www/portal-mrk/app/control"
    exit 1
fi

if [ ! -d "$DIR" ]; then
    echo "❌ Pasta não existe: $DIR"
    exit 1
fi

echo "📂 Pasta: $DIR"
if [ "$DRY_RUN" -eq 1 ]; then
    echo "🔍 Modo: DRY-RUN (nada será renomeado)"
else
    echo "✍️  Modo: APLICAR renomeações"
fi
echo ""

# ---------- Processa ----------
TOTAL=0
RENOMEADOS=0
PULADOS=0

while IFS= read -r -d '' file; do
    TOTAL=$((TOTAL + 1))
    filename=$(basename "$file")

    # Já tem .class.php? pula
    case "$filename" in
        *.class.php)
            echo "⏭  [já OK]       $filename"
            PULADOS=$((PULADOS + 1))
            continue
            ;;
    esac

    # Monta o novo nome: tira o .php e coloca .class.php
    dir=$(dirname "$file")
    newname="${filename%.php}.class.php"
    newpath="$dir/$newname"

    # Se já existir um arquivo com o novo nome, não sobrescreve
    if [ -e "$newpath" ]; then
        echo "⚠️  [conflito]   $filename (já existe $newname)"
        PULADOS=$((PULADOS + 1))
        continue
    fi

    if [ "$DRY_RUN" -eq 1 ]; then
        echo "✅ [seria]       $filename  →  $newname"
    else
        mv "$file" "$newpath"
        echo "✅ [renomeado]   $filename  →  $newname"
    fi
    RENOMEADOS=$((RENOMEADOS + 1))

done < <(find "$DIR" -type f -name "*.php" ! -name "*.bak" -print0)

# ---------- Resumo ----------
echo ""
echo "========== RESUMO =========="
echo "Total analisados:  $TOTAL"
echo "✅ Renomeados:      $RENOMEADOS"
echo "⏭  Pulados:         $PULADOS"

if [ "$DRY_RUN" -eq 1 ]; then
    echo ""
    echo "💡 Rode sem --dry-run pra aplicar."
fi