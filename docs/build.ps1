Set-Location ../visualiser
npm update --save
npx webpack build
Set-Location ../docs
pip install --user -r requirements.txt --upgrade --upgrade-strategy eager
python -m sphinx . _build -E -a
