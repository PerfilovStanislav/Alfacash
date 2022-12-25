<p align="center"><a href="https://www.alfa.cash/ru" target="_blank"><img src="https://www.alfa.cash/f/img/light.cdb6c29.svg" width="400" alt="Alfacash Logo"></a></p>

## Тестовое задание

### Входные данные:
1. Исходная валюта. Задается в виде тикера. Например: ETH
2. Валюта к получению. Задается в виде тикера. Например: XRP
3. Количество исходной валюты. Например: 0.123

### Задача:  
Необходимо разработать систему для поиска наилучшего курса для покупки/продажи криптовалюты на
бирже. Если взять данные из примера, то нужно 0.123 ETH поменять на XRP, и Ваша задача найти
оптимальный путь обмена.

### Дополнительные условия:
1. Используем для расчета биржу Binance. Для разных аккаунтов на ней разные условия –
   используйте условия Вашего аккаунта.
2. У биржи есть комиссии по обмену. Их нужно учитывать при расчетах.
3. Пути обмена могут быть как прямые (ETH -> XRP), так и через 1, 2 и больше валют (ETH -> crypto1
   -> crypto2 -> … -> XRP). На практике получается, что прямая пара наиболее оптимальна, но это,
   возможно, не всегда.
4. Необходимо использовать библиотеку ccxt, предоставляющую интерфейс по работе с биржами.
5. Для расчета курсов необходимо использовать стаканы биржи. При этом на расчет оптимальных
   путей обмена уходит какое-то время. На этот момент не обращаем внимания, то есть один раз
   получили стаканы с биржи, дальше их используем.
6. Если начальная сумма большая, то в стакане суммы первых заявок не хватает, поэтому путей
   обмена может быть несколько. Например, если есть 100 ETH, то часть обменяется по прямой паре
   ETH -> XRP, часть обменяется через одну третью валюту, часть через другую третью валюту, часть
   через две другие валюты и т.д. Все эти пути обмена нужно показать, чтобы в итоге вся начальная
   сумма израсходовалась.
7. Понятно, что валют на бирже сотни и длина пути тоже может быть сколь угодно большой, поэтому
   время работы алгоритма при таком условии может стремиться к бесконечности. Чтобы этого не
   было мы предлагаем ограничить список валют, через которые будут искаться пути, наиболее
   ликвидными. Например, Вы можете взять первые 10 валют из этого списка:
   https://coinmarketcap.com/ru/ Или можете не первые 10 (так как в них много стейблкоинов), а другие,
   например, LTC, TRX, DOT или другие на Ваше усмотрение. Но вот длина пути действительно может
   быть любой. Но если алгоритм верный, то дальнейшее увеличение пути будет приводить к
   уменьшению конечной суммы.
8. Вывод результата можно реализовать одним из двух способов: простейшая html страница через
   view и blade шаблонами с использованием любых стилей и js; либо это может быть json api запрос с
   json ответом.
9. Что должно быть в ответе: список путей обмена, конечная сумма, итоговые комиссии. Каждый путь
   обмена должен содержать: тикеры валют по пути (например ETH -> USDT -> XRP), сумму на каждом
   этапе обмена, конечную сумму, комиссии.
10. Реализовать необходимо, используя последнюю версию Laravel.
11. PHP 8.0
12. Никаких дополнительных пакетов, кроме ccxt использовать не надо.
13. При оценке также будет учитываться качество кода: читаемые названия переменных и методов,
    phpdoc.

## Решение
Решим данную задачу с помощью графа. 
1. Находим все возможные пути от одной вершины (валюты) до другой.
2. Получаем стаканы из стороннего сервиса для всех пар.
3. Находим оптимальный путь (самый выгодный курс по конвертациям)
4. Производим обмен
5. Запоминаем сколько потратили одной валюты и получили другой 

### Входные данные
```JSON
{
    "pair": "ETH_XRP",
    "amount": 10
}
```

### Результат
```JSON
{
    "total": {
        "in": 10,
        "out": 34639.19833424322,
        "rate": 3463.919833424322,
        "details": {
            "ETH->XRP": {
                "total": {
                    "in": 5.7572331000000005,
                    "out": 19947.033,
                    "rate": 3464.6908772896477
                },
                "details": {
                    "ETH->XRP": {
                        "in": 5.7572331000000005,
                        "out": 19947.033,
                        "rate": 3464.6908772896477
                    }
                }
            },
            "ETH->USDT->XRP": {
                "total": {
                    "in": 4.242766899999999,
                    "out": 14692.165334243224,
                    "rate": 3462.8735635330872
                },
                "details": {
                    "ETH->USDT": {
                        "in": 4.242766899999999,
                        "out": 5178.2897038909305,
                        "rate": 1220.49828
                    },
                    "USDT->XRP": {
                        "in": 5178.2897038909305,
                        "out": 14692.165334243224,
                        "rate": 2.8372621414370913
                    }
                }
            }
        }
    }
}
```
