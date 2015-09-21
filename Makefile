VERSION := 1.0.0

test:
	bin/phpunit

release:
	cp -ar src cf7ghq
	zip -r cf7ghq.zip cf7ghq
	rm -rf cf7ghq
	mv cf7ghq.zip build/

deploy:
	-bin/linux/amd64/github-release delete -u niteoweb -r cf7ghq -t v$(VERSION)
	-bin/linux/amd64/github-release delete -u niteoweb -r cf7ghq -t latest
	bin/linux/amd64/github-release release -u niteoweb -r cf7ghq -t v$(VERSION)
	bin/linux/amd64/github-release release -u niteoweb -r cf7ghq -t latest
	bin/linux/amd64/github-release upload -u niteoweb -r cf7ghq -t v$(VERSION) -f build/cf7ghq.zip -n cf7ghq.zip
	bin/linux/amd64/github-release upload -u niteoweb -r cf7ghq -t latest -f build/cf7ghq.zip -n cf7ghq.zip