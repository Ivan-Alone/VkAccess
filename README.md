# VkAccess

Библиотека для получения прямого доступа к токену авторизации ВКонтакте, без назойливых окошек, нужен всего лишь логин и пароль.

Отлично поможет работать с API ВКонтакте из консоли без браузера, поможет встроить ВКонтакте с полным доступом в ваш сайт, игру (например, чат ВК в Minecraft) и так далее.

Библиотека тестовая, изначально написана на PHP, позже без изменений алгоритма переведена на Java. Обе версии доступны (PHP - ООП).

### Описание функционала

#### Содержание

##### Список классов:
* VkAccess
* VkAccess.VkApp

##### Список функций:
* VkAccess.getVK()
* VkAccess.getAccessToken()
* VkAccess.getUserID()
* VkAccess.invoke(func)
* VkAccess.invoke(func, par)

#### Описание классов

##### VkAccess
Базовый класс авторизации в библиотеке. Является публичным нестатическим классом, то есть для использования необходимо создать его инстанцию.

Создание инстанции:

PHP:

```$vk = new VkAccess($app, $login, $password);```

Java:

```VkAccess vk = new VkAccess(app, login, password);```

Значения переменных:

- VkApp app - объект приложения ВКонтакте
- String login - логин пользователя (телефон, e-mail...)
- String password - пароль

##### VkApp
Вспомогательный класс, являющийся объектом приложения ВКонтакте, созданного на сайте https://vk.com/dev
Нужен для передачи идентификаторов приложения в объект авторизации.

Создание инстанции:

PHP:

```$app = new VkApp($id, $permissions);```

Java:

```VkAccess.VkApp app = new VkAccess.VkApp(id, permissions);```


Значения переменных:

- int id - идентификатор приложения ВКонтакте
- String/String[] permissions - права, требуемые приложению от пользователя

Стоит обратить внимание, что права приложения можно передать в конструктор класса как массивом, так и строкой.

Т.е. так:

```VkAccess.VkApp app = new VkAccess.VkApp(123456, "photos,friends,audio,video,notes");```

Либо так:

```VkAccess.VkApp app = new VkAccess.VkApp(123456, new String[]{"photos", "friends", "audio", "video", "notes"});```

#### Описание функций

##### VkAccess.getVK()

Возвращает информационный массив (в Java-версии - Map<String, Object>) со всеми параметрами текущего объекта класса (логин, пароль, токен доступа и прочее)

Типы данных:

* PHP
  + array()
* Java
  + Map<String, Object>

##### VkAccess.getAccessToken()

Возвращает токен доступа

Типы данных:

* PHP
  + String
* Java
  + String

##### VkAccess.getUserID()

Возвращает идентификатор текущего пользователя (необходим для некоторых запросов к API ВКонтакте)

Типы данных:

* PHP
  + String
* Java
  + int

##### VkAccess.invoke(func)

Выполняет запрос без параметров к API ВКонтакте от имени авторизованного пользователя. Возвращает ответ от сервера API ВКонтакте в формате JSON. В PHP-версии происходит автоматический парсинг текста JSON функцией json_decode.

Значения переменных:

- String func - название метода API

Типы данных:

* PHP
  + stdClass Object
* Java
  + String

##### VkAccess.invoke(func, par)

Выполняет запрос к API ВКонтакте от имени авторизованного пользователя. Возвращает ответ от сервера API ВКонтакте в формате JSON. В PHP-версии происходит автоматический парсинг текста JSON функцией json_decode.

Значения переменных:

- String func - название метода API
- String par - параметры метода API (в формате параметр1=тест&параметр2=чтото)

Типы данных:

* PHP
  + stdClass Object
* Java
  + String


#### Примеры программ с использованием этой библиотеки:

PHP:

```
<?php 	
    define('LOGIN', '88005553535');	
    define('PASSWORD', 'proshepozvonit');		

    include 'VkAccess.class.php';

    $app = new VkApp(123456, array('photos', 'friends', 'wall', 'messages', 'offline'));	
    $vk = new VkAccess($app, LOGIN, PASSWORD);			

    print_r($vk->invoke('photos.getAll'));  
    print_r($vk->invoke('messages.getHistory', 'user_id=123654&count=137'));	
    print_r($vk->invoke('wall.get', 'owner_id=123654&count=137&offset=666'));    
    print_r($vk->invoke('friends.get', 'count=137&order=random&count=282'));  

?>
```

Java:

```
import ru.ivan_alone.api.vkauthlib.VkAccess;
import ru.ivan_alone.api.vkauthlib.VkAccess.VkApp;

public class Me {

    private static final String LOGIN = "88005553535";
    private static final String PASSWORD = "proshepozvonit";
    
    public static void main(String[] args) {
        VkApp app = new VkApp(123456, new String[]{"photos", "friends", "wall", "messages", "offline"});  
        VkAccess vk = new VkAccess(app, LOGIN, PASSWORD);      
                
        System.out.print_ln(vk.invoke("photos.getAll"));
        System.out.print_ln(vk.invoke("messages.getHistory", "user_id=123654&count=137"));        
        System.out.print_ln(vk.invoke("wall.get", "owner_id=123654&count=137&offset=666"));        
        System.out.print_ln(vk.invoke("friends.get", "count=137&order=random&count=282"));
        
    }
}
```

### Итог
Надеюсь, вам понравится такой полезный функционал, как простой доступ к токену ВКонтакте, без страшных окон, перехватов и прочих ненужных вещей. 

Пользоваться библиотекой очень просто, и она не перегружена ненужным функционалом - всё остальное каждый разработчик может написать самомтоятельно, благо API ВКонтакте очень хорошо документирован (https://vk.com/dev/).

Следует учесть, что библиотека входит во ВКонтакте, представляясь браузером Mozila Firefox на операционной системе Windows 10 x64. Авторизованному пользователю придёт соответствующее электронное письмо, информирующее его о входе, а также push-уведомление на смартфон (при наличии).

Использован модифицированный User-Agent, для того, чтобы администрация ВКонтакте могла запретить использовать библиотеку, не вынося мозг разработчику.

```
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; rv:54.0) Gecko/20100101 Firefox/54.0 VkAuthLib/0.0.1 VkAccess/0.0.1
```

Крайне не рекомендую использовать мою библиотеку в коммерческих целях, а также для осуществления несанкционированной деятельности. А если вам уж очень захотелось - хотя бы поменяйте User-Agent (несложно найти в исходном тексте библиотеки, благо он небольшой) на что-нибудь своё. Вы же не хотите создать мне проблемы, правда?

Можете скинуть пару рублей на карту, если хотите: 4890 4940 4996 4380 (Visa)

Приятного пользования =)
