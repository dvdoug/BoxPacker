Set-Location ../playground
npm update --save
npm run build
Set-Location ../docs

# Automatically uses the current folder's name to create a unique external venv folder
$projectName = Split-Path -Leaf $PSScriptRoot
$env:UV_PROJECT_ENVIRONMENT = "$HOME\.uv-envs\$projectName"

uv lock --upgrade
uv run python -m sphinx . _build -E -a
Write-Host "Serving _build at http://127.0.0.1:8000/visualiser.html"
uv run python -m http.server -d _build 8000
