docker build -t sphinxrtd .
docker run -it --rm --name sphinx -v ${PWD}/..:/code -w="/code/docs" sphinxrtd make html
