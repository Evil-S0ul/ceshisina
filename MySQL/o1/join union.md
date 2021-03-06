<style type="text/css">
    h2,h3,h4,h5{color:#428BD1;}
</style>
# [MySQL（九）之数据表的查询详解（SELECT语法）二][0]

**阅读目录(Content)**

* [一、多表查询][1]
    * [1.1、取别名][2]
    * [1.2、普通双表查询][3]
    * [1.3、内连接查询][4]
    * [1.4、外连接查询][5]
        * [1.4.1、左外连接][6]
        * [1.4.2、右外连接][7]
    * [1.5、复合条件查询][8]
* [二、子查询][9]
    * [2.1、带ANY、SOME、ALL关键字的子查询][10]
    * [2.2、带EXISTS关键字查询][11]
    * [2.3、带比较运算符的子查询][12]
* [三、合并查询][13]
    * [3.1、UNION关键字][14]
    * [3.2、UNION[ALL]的使用][15]
    * [3.3、实例][16]
* [四、使用正则表达式查询][17]

上一篇讲了比较简单的单表查询以及MySQL的组函数，这一篇给大家分享一点比较难得知识了，关于多表查询，子查询，左连接，外连接等等。希望大家能都得到帮助！

在开始之前因为要多表查询，所以搭建好环境：

1）创建数据表suppliers

前面已经有一张表是book表，我们在建立一张suppliers(供应商)表和前面的book表对应。

也就是说 让book中s_id字段值指向suppliers的主键值，创建一个外键约束关系。

![][18]

其实这里并没有达到真正的外键约束关系，只是模拟，让fruits中的s_id中的值 能匹配到 suppliers 中的主键值，通过手动添加这种数据，来达到这种关系。

反正是死数据，也不在添加别的数据，就不用建立外键约束关系了，这里要搞清楚。

2）插入数据

![][19]

[回到顶部(go to top)][20]

# 一、多表查询

##  1.1、取别名 

1）为表取别名

因为是对两张表进行查询了，那么每次写表名的话就有点麻烦，所以用一个简单别名来代表表名

格式：表名 AS 别名

2）为字段取别名

给字段名取别名的原因是有些字段名是为了显示更加清楚

举例：select b_price as '价格' from book;

![][21]

##  1.2、普通双表查询 

需求：查询书的编号、书的名字、书的批发商编号、书的批发商名字

分析：看下要求，就知道要查询两张表，如果需要查询两张表，那么两张表的关系必定是外键关系，或者类似于外键关系(类似于也就是说 两张表并没有真正加外键约束，

但是其特点和外键是一样的，就像上面我们手动创建的两张表一样，虽然没有设置外键关联关系，但是其特性跟外键关系是一样的 。) 

    select b.b_id,b.b_name,s.s_id,s.s_name from book as b,suppliers as s where b.s_id=s.s_id;

![][22]

注意： 第一个执行的是FROM， 所以上面为表取别名，在语句的任何地方的可以使用

##  1.3、内连接查询 

了解了上面的两张表基本的连接查询后， 内连接查询就很简单了，因为内连接跟上面的作用是一样的，唯一的区别就是语法的不一样。

格式： 表名  INNER JOIN 表名 ON 连接条件

需求：：查询书的编号、书的名字、书的批发商编号、书的批发商名字（这个和上面的一样，我们看一下语法上有什么不一样的）

    select b.b_id,b.b_price,s.s_id,s.s_name from book as b inner join suppliers as s on b.s_id=s.s_id;

![][23]

其实还有一种自然连接：涉及到的两张表都是同一张表。

举例：查看书id为g2的供应商供应的其他书？

    select b2.b_id,b2.b_name from book as b1 inner join book as b2 on b1.s_id=b2.s_id and b1.b_id='g2';

![][24]

分析：把book表分开看成是两张完全一样的表，在b1表中找到b_id='g2'的s_id，然后到b2这张表中去查找和该s_id相等的记录，也就查询出来了问题所需要的结果。

还有另一种方法，不用内连接查询， 通过子查询也可以做到 ，下面会讲解，这里先给出答案，到时可以回过头来看看这个题。

    select b_id,b_name from book where s_id=(select s_id from book where b_id='g2'); 

![][25]

结果和上面的一样

## 1.4、外连接查询 

内连接是将 符合查询条件(符合连接条件)的行返回，也就是相关联的行就返回 。

外连 接除了返回相关联的行之外，将没有关联的行也会显示出来 。

为什么需要将不没关联的行也显示出来呢？这就要根据不同的业务需求了，就比如，order和customers，顾客可以有订单也可以没订单，现在需要知道所有顾客的下单情况，而我们不能够只查询出有订单的用户，

而把没订单的用户丢在一边不显示，这个就跟我们的业务需求不相符了，有人说，既然知道了有订单的顾客，通过单表查询出来不包含这些有订单顾客，不就能达到我们的要求吗，这样是可以，但是很麻烦，如何能够将其一起显示并且不那么麻烦呢？为了解决这个问题，就有了外连接查询这个东西了。

### 1.4.1、左外连接 

格式： 表名 LEFT JOIN 表名 ON 条件；

返回 包括左表中的所有记录 和 右表中连接字段相等的记录 ，通俗点讲，就是除了显示相关联的行，还会将左表中的所有记录行度显示出来。

简单的说： 连接两张表，查询结果包含左边表的所有数据以及右边表和左边表有关系的数据 。

实例：为了演示我们的效果我们给suppliers添加两条数据

![][26]

    select s.s_id,s.s_name,b.b_id,b.b_name

    from suppliers as s left join book as b

    on s.s_id=b.s_id;

![][27]

分析：suppliers表是在LEFT JOIN的左边，所以将其中所有记录度显示出来了，有关联项的，也有没有关联项的。这就是左外连接的意思，将左边的表所有记录都显示出来(前提是按照我们所需要的字段，

也就是SELECT 后面所选择的字段)。如果将suppliers表放LEFT JOIN的右边，那么就不会在显示80和90这两条记录了。来看看

![][28]

### 1.4.2、右外连接 

格式： 表名 RIGHT JOIN 表名 ON 条件

 返回包括右表中的所有记录和右表中连接字段相等的记录 。其实跟左外连接差不多，就是将右边的表给全部显示出来

    select s.s_id,s.s_name,b.b_id,b.b_name

    from suppliers as s right join book as b

    on s.s_id=b.s_id;

![][29]

## 1.5、复合条件查询 

在连接查询(内连接、外连接)的过程中，通过添加过滤条件，限制查询的结果，使查询的结果更加准确，通俗点讲，就是将连接查询时的条件更加细化。

1）在book和suppliers表中使用INNER JOIN语法查询suppliers表中s_id为70的供应商的供货信息？

    select s.s_id,s.s_name,b.b_id,b.b_name

    from book as b inner join suppliers as s

    on s.s_id=b.s_id and s.s_id=70;

![][30]

2）在fruits表和suppliers表之间，使用INNER JOIN语法进行内连接查询，并对查询结果进行排序

    select s.s_id,s.s_name,b.b_id,b.b_name

    from book as b inner join suppliers as s

    on s.s_id=b.s_id order by b.s_id; //对b.s_id进行升序。默认的是ASC，所以不用写。

![][31]

[回到顶部(go to top)][20]

# 二、子查询 

子查询，将查询一张表得到的结果来充当另一个查询的条件，这样嵌套的查询就称为子查询。

搭建环境:

表tb1：

![][32]

表tb2：

![][33]

## 2.1、带ANY、SOME、ALL关键字的子查询 

![][34]

1） ANY关键字 接在一个 比较操作符的后面 ，表示若 与子查询返回的任何值比较为TRUE，则返回TRUE，通俗点讲，只要满足任意一个条件，就返回TRUE 。

 SOME关键字和ANY关键字的用法一样，作用也相同 。

实例：

    select num1 from tb1 where num1> any(select num2 from tb2); //这里就是将在 tb2表中查询的结果 放在 前一个查询语句中充当条件参数 。只要num1大于其结果中的任意一个数，那么就算匹配。

![][35]

2） ALL关键字 表示需要同时满足所有条件

    select num1 from tb1 where num1> all(select num2 from tb2); //num1需要大于所有的查询结果才算匹配 

![][36]

##  2.2、带EXISTS关键字查询 

EXISTS关键字后面的参数是任意一个子查询，如果子查询有返回记录行，则为TRUE，外层查询语句将会进行查询，如果子查询没有返回任何记录行，则为FALSE，外层查询语句将不会进行查询。

![][37]

##  2.3、带比较运算符的子查询 

除了使用关键字ALL、ANY、SOME等之外，还可以使用普通的比较运算符。来进行比较。比如我们上面讲解内连接查询的时候，就用过子查询语句，并且还是用的=这个比较运算符。

[回到顶部(go to top)][20]

# 三、合并查询

## 3.1、UNION关键字 

合并结果集， 将多个结果集拼接在一起 。合并的时候 只关注列数相同，不关注数据类型 。但是在 没有特殊需求的情况下最好不要将数据类型不同的列进行合并 。

当数据类型不同的情况下进行合并时，合并之后列的数据类型是varchar类型。 在合并的时候会消除重复的行，不消除重复的行，可使用union all。

 利用UNION关键字，可以将查询出的结果合并到一张结果集中，也就是通过UNION关键字将多条SELECT语句连接起来，注意，合并结果集， 只是增加了表中的记录，并不是将表中的字段增加，仅仅是将记录行合并到一起。其显示的字段应该是相同的，不然不能合并 。

## 3.2、UNION[ALL]的使用 

UNION：不使用关键字ALL，执行的时候会删除重复的记录，所有返回的行度是唯一的，

UNION ALL：不删除重复航也不对结果进行自动排序。

格式：

SELECT 字段名,... FROM 表名

UNION[ALL]

SELECT 字段名,... FROM 表名

## 3.3、实例 

1）查询书价小于50，查询s_id为50或70的书的信息，使用union

![][38]

使用UNION，而不用UNION ALL的话，重复的记录就会被删除掉。

[回到顶部(go to top)][20]

# 四、使用正则表达式查询 

使用REGEXP关键字来指定正则表达式，画一张表格，就能将下面所有的度覆盖掉。

![][39]

1）查询一特定字符开头或字符串开头的记录

    select * from book where b_name REGEXP '^j'; //以j开头的记录

![][40]

注意：唯一的差别就在正则表达式不一样，一般使用这种模糊查询，使用MySQL中的'_'和'%'就已经足够了。

2） 查询以特定字符或字符串结尾的记录

3）用符号"."来替代字符串中的任意一个字符

4）使用"*"和"+"来匹配多个字符

5）匹配指定字符串

6）匹配指定字符中的任意一个

7）匹配指定字符以外的字符

8）使用{n,}或者{n,m}来指定字符串连续出现的次数

[0]: http://www.cnblogs.com/zhangyinhua/p/7506034.html
[1]: #_label0
[2]: #_lab2_0_0
[3]: #_lab2_0_1
[4]: #_lab2_0_2
[5]: #_lab2_0_3
[6]: #_label3_0_3_0
[7]: #_label3_0_3_1
[8]: #_lab2_0_4
[9]: #_label1
[10]: #_lab2_1_0
[11]: #_lab2_1_1
[12]: #_lab2_1_2
[13]: #_label2
[14]: #_lab2_2_0
[15]: #_lab2_2_1
[16]: #_lab2_2_2
[17]: #_label3
[18]: ./img/226415053.png
[19]: ./img/494880425.png
[20]: #_labelTop
[21]: ./img/908846686.png
[22]: ./img/1547493292.png
[23]: ./img/730456062.png
[24]: ./img/648663835.png
[25]: ./img/262993614.png
[26]: ./img/1292326132.png
[27]: ./img/1676441174.png
[28]: ./img/1349289396.png
[29]: ./img/439849493.png
[30]: ./img/1742701663.png
[31]: ./img/1864564127.png
[32]: ./img/1724998352.png
[33]: ./img/721992291.png
[34]: ./img/1094298714.png
[35]: ./img/573857669.png
[36]: ./img/117245299.png
[37]: ./img/254228712.png
[38]: ./img/319119759.png
[39]: ./img/1705023559.png
[40]: ./img/1812895026.png