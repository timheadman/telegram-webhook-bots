NAME = telegram-apache
EXEC = docker run --restart=always --detach --publish 8081:80 --name $(NAME) $(NAME)
SERVER = tim@10.11.12.252

.PHONY: clean
#Останавливаем контейнер и удаляем образ.
clean:
	docker container stop $(NAME); \
	docker container remove $(NAME); \
	docker image remove $(NAME)

.PHONY: build
#Создаем образ, создаем файл со скриптом.
build:
	@docker build -t $(NAME) .
	@docker image list $(NAME)

.PHONY: copy
#Удаленный запуск на сервер, сохраняем образ, отправляем все на сервер, запускаем удаленно.
copy:
	docker image save $(NAME) | bzip2 > $(NAME).tar.bz2
	scp -v $(NAME).tar.bz2 $(SERVER):/home/tim/
	ssh $(SERVER) "docker load < /home/tim/$(NAME).tar.bz2 && $(EXEC) && rm -f /home/tim/$(NAME).tar.bz2"
	rm -vf $(NAME).tar.bz2
	docker image rm $(NAME)

.PHONY: run
#Локальный запуск с сервера.
run:
	$(EXEC)
	@docker container list


