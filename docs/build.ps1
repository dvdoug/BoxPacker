docker build -t node -f Dockerfile.node .
docker build -t sphinxrtd -f Dockerfile.sphinx .
docker run -it --rm --name node -v ${PWD}/..:/code -w="/code/docs/visualiser" node npx webpack build
docker run -it --rm --name sphinx -v ${PWD}/..:/code -w="/code/docs" sphinxrtd make html
