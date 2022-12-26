NAME = telegram-webhook-bots
RUNNER_NAME = run-$(NAME)-container.sh

.PHONY: build
#Создаем образ, сохраняем, архивируем.
build:
	@docker build -t $(NAME) . 
	docker image save $(NAME) | bzip2 > $(NAME).tar.bz2

.PHONY: copy
#Создаем файл со скриптом, отправляем все на сервер, запускаем удаленно.
copy:
	echo "docker run --restart=always --detach --publish 8081:80 --name $(NAME) $(NAME)" > $(RUNNER_NAME) 
	chmod +x $(RUNNER_NAME) 
	scp -v $(NAME).tar.bz2 $(RUNNER_NAME) tim@10.11.12.252:/home/tim/ 
	ssh server "docker load < /home/tim/$(NAME).tar.bz2 && rm -f /home/tim/$(NAME).tar.bz2" 
	rm -vf $(NAME).tar.bz2 $(RUNNER_NAME)

