Set-Location ../visualiser
npm update --save
npm run build
Set-Location ../docs
uv lock --upgrade
uv run python -m sphinx . _build -E -a
