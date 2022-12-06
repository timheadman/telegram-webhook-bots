.PHONY: build
build:
	@docker build -t telegram_bots . 
	
.PHONY: run
build:
	@docker run --detach --publish 8081:80 --name running-telegram_bots telegram_bots

