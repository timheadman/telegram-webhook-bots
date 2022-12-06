.PHONY: build
build:
	@docker build -t telegram_bots . 
	
.PHONY: test
test:
	@docker run --interactive --tty --rm --publish 8081:80 --name running-telegram_bots telegram_bots

.PHONY: production
production:
	@docker run --restart=always --detach --publish 8081:80 --name running-telegram_bots telegram_bots

