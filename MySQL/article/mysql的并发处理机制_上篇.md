# [mysql的并发处理机制_上篇][0]

**阅读目录(Content)**

<font face=微软雅黑>

* [1 什么是MVCC][1]
* [2 Innodb的MVCC][2]
* [3 Two Phase Locking][3]
* [4 数据不一致情况][4]
    * [4.1 脏读][5]
    * [4.2 丢失更新][6]
    * [4.3 不可重复读][7]
    * [4.4 幻读][8]
* [5 innodb的隔离级别][9]
    * [5.1 隔离级别介绍][10]
    * [5.2 隔离级别测试][11]
        * [5.2.2 Read Committed][12]
* [PS： semi-consistent read][13]

回来写博客，少年前端时间被django迷了心魄 

- - -

如果转载，请注明博文来源： [www.cnblogs.com/xinysu/][15] ，版权归 博客园 苏家小萝卜 所有。望各位支持！

- - -


## **1 什么是MVCC**

MVCC全称是： **Multiversion concurrency control**，多版本并发控制，提供并发访问数据库时，对事务内读取的到的内存做处理，用来避免写操作堵塞读操作的并发问题。

举个例子，程序员A正在读数据库中某些内容，而程序员B正在给这些内容做修改（假设是在一个事务内修改，大概持续10s左右），A在这10s内 则可能看到一个不一致的数据，在B没有提交前，如何让A能够一直读到的数据都是一致的呢？

有几种处理方法，第一种： 基于锁的并发控制，程序员B开始修改数据时，给这些数据加上锁，程序员A这时再读，就发现读取不了，处于等待情况，只能等B操作完才能读数据，这保证A不会读到一个不一致的数据，但是这个会影响程序的运行效率。还有一种就是：MVCC，每个用户连接数据库时，看到的都是某一特定时刻的数据库快照，在B的事务没有提交之前，A始终读到的是某一特定时刻的数据库快照，不会读到B事务中的数据修改情况，直到B事务提交，才会读取B的修改内容。

一个支持MVCC的数据库，在更新某些数据时，并非使用新数据覆盖旧数据，而是标记旧数据是过时的，同时在其他地方新增一个数据版本。因此，同一份数据有多个版本存储，但只有一个是最新的。

MVCC提供了 时间一致性的 处理思路，在MVCC下读事务时，通常使用一个时间戳或者事务ID来确定访问哪个状态的数据库及哪些版本的数据。读事务跟写事务彼此是隔离开来的，彼此之间不会影响。假设同一份数据，既有读事务访问，又有写事务操作，实际上，写事务会新建一个新的数据版本，而读事务访问的是旧的数据版本，直到写事务提交，读事务才会访问到这个新的数据版本。

MVCC有两种实现方式，第一种实现方式是将数据记录的多个版本保存在数据库中，当这些不同版本数据不再需要时，垃圾收集器回收这些记录。这个方式被PostgreSQL和Firebird/Interbase采用，SQL Server使用的类似机制，所不同的是旧版本数据不是保存在数据库中，而保存在不同于主数据库的另外一个数据库tempdb中。第二种实现方式只在数据库保存最新版本的数据，但是会在使用undo时动态重构旧版本数据，这种方式被Oracle和MySQL/InnoDB使用。

这部分可以查阅维基百科：[https://en.wikipedia.org/wiki/Multiversion_concurrency_control][17]


# **2 Innodb的MVCC**

在Innodb db中，无论是聚簇索引，还是二级索引，每一行记录都包含一个 `DELETE bit`，用于表示该记录是否被删除， 同时，聚簇索引还有两个隐藏值：`DATA_TRX_ID`，`DATA_ROLL_PTR`。`DATA _TRX_ID`表示产生当前记录项的事务ID，这个ID随着事务的创建不断增长；`DATA _ROLL_PTR`指向当前记录项的`undo`信息。

1. 无论是聚簇索引，还是二级索引，只要其键值更新，就会产生新版本。将老版本数据`deleted bti`设置为1；同时插入新版本。
1. 对于聚簇索引，如果更新操作没有更新primary key，那么更新不会产生新版本，而是在原有版本上进行更新，老版本进入undo表空间，通过记录上的undo指针进行回滚。
1. 对于二级索引，如果更新操作没有更新其键值，那么二级索引记录保持不变。
1. 对于二级索引，更新操作无论更新primary key，或者是二级索引键值，都会导致二级索引产生新版本数据。
1. 聚簇索引设置记录`deleted bit`时，会同时更新`DATA_TRX_ID`列。老版本`DATA_TRX_ID`进入undo表空间；二级索引设置`deleted bit`时，不写入undo。

**MVCC只工作在REPEATABLE READ和READ COMMITED隔离级别下。READ UNCOMMITED不是MVCC兼容的，因为查询不能找到适合他们事务版本的行版本；它们每次都只能读到最新的版本。SERIABLABLE也不与MVCC兼容，因为读操作会锁定他们返回的每一行数据 。**

在MVCC中，读操作分为两类：当前读跟快照读，当前读返回最新记录，会加锁，保证该记录不会被其他事务修改；快照读，读取的是记录的某个版本（有可能是最新版本也有可能是旧版本），不加锁。

快照读：RU,RC,RR隔离级别下，`select * from tbname where ....`

当前读：

1. select * from tbname where .... **for update （加X锁）**
1. select * from tbname where .... **lock in share mode（加S锁）**
1. insert into tbname .... （加X锁，注意如果有unique key的情况）
1. delete from tbname ... （加X锁）
1. update tbname set ... where .. （加X锁）

本部分参考：[http://hedengcheng.com/?p=148][18]


# **3 Two Phase Locking**

2-PL，也就是两阶段锁，锁的操作分为两个阶段：加锁、解锁。先加锁，后解锁，不相交。加锁时，读操作会申请并占用S锁，写操作会申请并占用X锁，如果对所在记录加锁有冲突，那么会处于等待状态，知道加锁成功才惊醒下一步操作。解锁时，也就是事务提交或者回滚的时候，这个阶段会释放该事务中所有的加锁情况，进行一一释放锁。

假设事务对记录A和记录B都有操作，那么，其加锁解锁按照逐行加锁解锁顺序，如下：

```
BEGIN
LOCK A
READ A
A:A+100
WRITE A
UNLOCK A
LOCK B
READ B
UNLOCK B
COMMIT
```

两阶段锁还有几种特殊情况：`conservative`（保守）、`strict`（严格）、`strong strict`（强严格），这三种类型在加锁和释放锁的处理有些不一样。

1. `conservative` 
    * 在事务开始的时候，获取需要的记录的锁，避免在操作期间逐个申请锁可能造成的锁等待，`conservative 2PL` 可以避免死锁
1. `strict` 
    * 仅在事务结束的时候（commit or rollback），才释放所有 `write lock`，`read lock` 则正常释放
1. `strong strict` 
    * 仅在事务结束的时候（commit or rollback），才释放所有锁，包括write lock 跟 read lock 都是结束后才释放。

这部分可以查看维基百科：[https://en.wikipedia.org/wiki/Two-phase_locking][20]，


# **4 数据不一致情况**

## **4.1 脏读**

读取未提交事务中修改的数据，称为脏读。

举例，表格 A （name,age），记录1为name='xinysu'，age=188

![][21]

这里，事务2 中读出来的数据是 （name，age）=（'xinysu',299），这一条是 事务1中未提交的记录，属于脏数据。

## **4.2 丢失更新**

多个更新操作并发执行，导致某些更新操作数据丢失。

举例，表格 A （name,age），记录1为name='xinysu'，age=188。并发2个更新操作如下：

![][22]

正常情况下，如果是事务1操作后，age为288，事务2再进行288+100=388，但是实际上，事务2的操作覆盖事务1的操作，造成了事务1的更新丢失。

## **4.3 不可重复读**

同个事务多次读取同一条存在的记录，但是读取的结构不一致，称之为不可重复读。

举例，表格 A （name,age），记录1为name='xinysu'，age=188。操作如下：

![][23]

事务1第一次读出来的结构是name='xinysu'，age=188，第二次读出来的结果是name='xinysu'，age=288，同个事务中，多次读取同一行存在的记录，但结果不一致的情况，则为不可重复读。

## **4.4 幻读**

同个事务多次读取某段段范围内的数据，但是读取到底行数不一致的情况，称之为幻读。

举例，表格 A （name,age），记录1为name='xinysu'，age=188。操作如下：

![][24]

事务1中，第一次读取的结果行数有1行，如果事务2执行的是delete，则事务1第二次读取的为0行；如果事务2执行的是INSERT，则事务2第二次读取的行数是2行，前后记录数不一致，称之为幻读。


# **5 innodb的隔离级别**

## **5.1 隔离级别介绍**

1. `Read Uncommited` 
    * 简称 **`RU`**，读未提交记录，始终是读最新记录
    * 不支持快照读，都是当前读
    * 可能存在脏读、不可重复读、幻读等问题；

1. `Read Commited` 
    * 简称 **`RC`** ，读已提交记录
    * 支持快照读，读取版本有可能不是最新版本
    * 支持当前读，读取到的记录添加锁
    * * 不存在脏读、不可重复读
        * 存在幻读问题；

1. `Read Repeatable` 
    * 简称 **`RR`** ，可重复读记录
    * 支持快照读，读取版本有可能不是最新版本
    * 支持当前读，读取到的记录添加锁，并且对读取的范围枷锁，保证满足查询条件的记录不能够被insert进来
    * 不存在脏读、不可重复读及幻读情况；

1. `Read Serializable` 
    * 简称 **`RS`**，序列化读记录
    * 不支持快照读，都是当前读
    * select数据添加S锁，update\insert\delete数据添加X锁
    * 并发度最差，除非明确业务需求及性能影响，才使用，一般不建议在innodb中应用

## **5.2 隔离级别测试**测试各个隔离级别下的数据不一致情况。

    1.查看当前会话隔离级别
    select @@tx_isolation;
     
    2.查看系统当前隔离级别
    select @@global.tx_isolation;
     
    3.设置当前会话隔离级别
    set session transaction isolation level repeatable read;
     
    4.设置系统当前隔离级别
    set global transaction isolation level repeatable read;

**5.2.1 Read Uncommitted**

**所有事务隔离级别设置： set session transaction isolation level read Uncommited ;**

该隔离级别没有的快照读，所有读操作都是读最新版本，可以读未提交事务的数据。

测试1：update数据不提交，另起查询

测试结果：正常select可以查询到不提交的事务内容，属于脏读

![][25]

测试2：修改数据不提交，另起事务多次查询

测试结果：同个事务多次读取同一行记录结果不一致，属于重复读

![][26]

测试3：INSERT数据不提交，另起事务多次查询

测试结果：同个事务多次读取相同范围的数据，但是行数不一样，属于幻读

![][27]

测试4：不同事务对同一行数据进行update

测试结果：由于INNODB有锁机制，所有所有update都会持有X锁互斥，并不会出现事务都提交成功情况下的丢失更新，所以四个隔离级别都可以避免丢失更新问题。

![][28]

**总结：没有快照读，都是当前读，所有读都是读可以读未提交记录，存在脏读、不可重复读、幻读等问题。**

### **5.2.2 Read Committed**

**所有事务隔离级别设置： set session transaction isolation level read committed ;**

由于该隔离级别支持快照读，不添加`for update`跟`lock in share mode`的select 查询语句，使用的是快照读，读取已提交记录，不添加锁。所以测试使用当前读的模式测试，添加`lock in share mode`，添加S锁。

测试1：update数据不提交，另起查询

测试结果：由于当前读持有S锁，导致update申请X锁处于等待情况，无法更新，同个事务内的多次查询结果一致，无脏读及不可重复读情况。

![][29]

测试2：INSERT数据不提交，另起事务多次查询

测试结果：同个事务多次读取相同范围的数据，但是行数不一样，属于幻读（这里注意，如果insert 分为beigin；commit，一直不commit的话，3的查询会处于等待情况，因为它需要申请的S锁被 insert的X锁所堵塞） 

![][30]

测试3：快照读测试

测试结果：同个事务多次读取相同记录，读取的都是已提交记录，不存在脏读及丢失更新情况，但是存在不可重复读及幻读。 

![][31]

**总结：支持快照读，快照读 不存在脏读及丢失更新情况，但是存在不可重复读及幻读；****而当前读不存在脏读、不可重复读问题，存在幻读问题。**

### **5.2.3 Read Repeatable**

**所有事务隔离级别设置： set session transaction isolation level repeatable****read ;**

由于该隔离级别支持快照读，不添加for update跟`lock in share mode`的select 查询语句，使用的是快照读，不添加锁。所以测试使用当前读的模式测试，添加`lock in share mode`，添加S锁。

测试1：update数据不提交，另起查询

测试结果：由于当前读持有S锁，导致update申请X锁处于等待情况，无法更新，同个事务内的多次查询结果一致，无脏读及不可重复读情况。


![][33]

测试2：INSERT数据不提交，另起事务多次查询

测试结果：同个事务多次读取相同范围的数据，会有GAP锁锁定，故同个事务多次当前读结果记录数都是一致的，不存在幻读情况。

![][34]

测试3：快照读测试

测试结果：同个事务多次读取相同记录，不存在脏读及丢失更新、不可重复读及幻读等情况。

![][35]

**总结：支持快照读，快照读跟****当前读不存在脏读、不可重复读问题，存在幻读问题。**

### **5.2.4 Read Serializable**

**所有事务隔离级别设置： set session transaction isolation level****Serializable****;**

该隔离级别不支持快照读，所有SELECT查询都是当前读，并且持有S锁.

测试1：update数据不提交，另起查询；INSERT数据不提交，另起事务多次查询

测试结果：该隔离级别下所有select语句持有S锁，导致update申请X锁处于等待情况，INSERT申请X也被堵塞，同个事务内的多次查询结果一致，不存在脏读、不可重复读及幻读情况。

![][36]

**总结：无快照读，所有SELECT查询都是****当前读，不存在脏读、不可重复读问题，存在幻读问题。**

- - -

以为没了，not，还有一个概念这里没有提交，这里补充介绍下：`semi-consistent read`

- - -



# **PS： semi-consistent read**

**在read committed或者read uncommitted 隔离级别下**，有这样的测试现象：

测试表格及数据
```sql
CREATE TABLE `tblock` (

`id` int(11) NOT NULL AUTO_INCREMENT,

`name` varchar(10) DEFAULT NULL,

PRIMARY KEY (`id`)

) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

insert into tblock(name) select 'su';

insert into tblock(name) select 'xin';
```
测试1：两个update事务并发，分别update不同行，update条件列无索引

测试结果：两条update互不干扰，正常执行。

![][37]

测试2：update语句不提交，另起事务当前读操作

测试结果：当前读被堵塞，无法正常加X锁


![][39]

**问题点：为啥两个测试中的sql序号2，都是申请X锁，测试1可以正常申请情况，而测试2不行呢？**

正常情况下，where条件中的name列没有索引，故这个update操作是对全表做scan扫描加X锁，正常情况下，在第一个事务中，update语句没有提交的情况下，这个表格有一个表锁X，对每一行数据都无法申请S锁或者X锁，那么为什么 测试1 可以正常申请呢？

在这里，需要引入semi-constent-read，半一致性读。官网解释如下：

_semi consistent read：_

_A type of read operation used for UPDATE statements, that is a combination of read committed and consistent read. When an UPDATE statement examines a row that is already locked, InnoDB returns the latest committed version to MySQL so that MySQL can determine whether the row matches the WHERE condition of the UPDATE. If the row matches (must be updated), MySQL reads the row again, and this time InnoDB either locks it or waits for a lock on it. This type of read operation can only happen when the transaction has the read committed isolation level, or when the innodb_locks_unsafe_for_binlog option is enabled._

`semi-consistent read`是update语句在读数据的一种操作， 是`read committed`与`consistent read`两者的结合。update语句A在没有提交时，另外一个update语句B读到一行已经被A加锁的记录，但是这行记录不在A的where条件内，此时InnoDB返回记录最近提交的版本给B，由MySQL上层判断此版本是否满足B的update的where条件。若满足(需要更新)，则MySQL会重新发起一次读操作，此时会读取行的最新版本(并加锁)。`semi-consistent read`只会发生在`read committed`及 **read uncommitted**隔离级别，或者是参数`innodb_locks_unsafe_for_binlog`被设置为true。 对update起作用，对`select insert delete` 不起作用。这就导致了`update` 不堵塞，但是当前读的`select`则被堵塞的现象。

发生 `semi consitent read`的条件：

1. `update`语句
1. 执行计划时`scan`，`range scan or table scan`，不能时`unique scan`
1. 表格为聚集索引

总结如下：

![][40]

如果转载，请注明博文来源： www.cnblogs.com/xinysu/ ，权归 博客园 苏家小萝卜 所有。望各位支持！

</font>

[0]: http://www.cnblogs.com/xinysu/p/7260227.html
[1]: #_label0
[2]: #_label1
[3]: #_label2
[4]: #_label3
[5]: #_lab2_3_0
[6]: #_lab2_3_1
[7]: #_lab2_3_2
[8]: #_lab2_3_3
[9]: #_label4
[10]: #_lab2_4_0
[11]: #_lab2_4_1
[12]: #_label3_4_1_0
[13]: #_label5

[15]: http://www.cnblogs.com/xinysu/
[16]: #_labelTop
[17]: https://en.wikipedia.org/wiki/Multiversion_concurrency_control
[18]: http://hedengcheng.com/?p=148
[19]: #
[20]: https://en.wikipedia.org/wiki/Two-phase_locking
[21]: ./img/1330607402.png
[22]: ./img/753124538.png
[23]: ./img/1543976963.png
[24]: ./img/1411813995.png
[25]: ./img/1924827027.png
[26]: ./img/53126937.png
[27]: ./img/867185670.png
[28]: ./img/1913088425.png
[29]: ./img/1956428903.png
[30]: ./img/715439220.png
[31]: ./img/388510668.png

[33]: ./img/966427933.png
[34]: ./img/1265517150.png
[35]: ./img/1609844308.png
[36]: ./img/1203268621.png
[37]: ./img/1781256531.png

[39]: ./img/1685388309.png
[40]: ./img/65460081.png