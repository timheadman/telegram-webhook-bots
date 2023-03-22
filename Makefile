NAME = telegram-webhook-bots
RUN_SCRIPT_NAME = run-$(NAME)-container.sh
SERVER = tim@10.11.12.252

.PHONY: clean
#Останавливаем контейнер и удаляем образ.
clean:
	docker container stop $(NAME)
	docker container remove $(NAME)
	docker image remove $(NAME)

.PHONY: build
#Создаем образ, создаем файл со скриптом.
build:
	docker build -t $(NAME) . 
	echo "docker run --restart=on-failure --detach --publish 8081:80 --name $(NAME) $(NAME)" > $(RUN_SCRIPT_NAME) 
	#Добавить: --memory=?????MB 
	chmod +x $(RUN_SCRIPT_NAME) 
	@ls -l --color=auto *$(NAME)*

.PHONY: copy
#Удаленный запуск на сервер, сохраняем образ, отправляем все на сервер, запускаем удаленно.
copy:
	docker image save $(NAME) | bzip2 > $(NAME).tar.bz2
	scp -v $(NAME).tar.bz2 $(RUN_SCRIPT_NAME) $(SERVER):/home/tim/ 
	ssh $(SERVER) "docker load < /home/tim/$(NAME).tar.bz2 && ./$(RUN_SCRIPT_NAME) && rm -f /home/tim/$(NAME).tar.bz2" 
	rm -vf $(NAME).tar.bz2 $(RUN_SCRIPT_NAME)
	docker image rm $(NAME)

.PHONY: run
#Локальный запуск с сервера.
run:
	./$(RUN_SCRIPT_NAME)
	rm -vf $(RUN_SCRIPT_NAME)
	@docker container list
