.PHONY: build
build:
	@docker build -t telegram_webhook_bots . 
	@docker image save telegram_webhook_bots | bzip2 > telegram_webhook_bots.tar.bz2

.PHONY: copy
copy:
	@scp telegram_webhook_bots.tar.bz2 tim@10.11.12.252:/home/tim/ 
	@ssh server "docker load < /home/tim/telegram_webhook_bots.tar.bz2 && rm -f /home/tim/telegram_webhook_bots.tar.bz2" 

.PHONY: run
run:
	@docker run --restart=always --detach --publish 8081:80 --name telegram_webhook_bots telegram_webhook_bots
