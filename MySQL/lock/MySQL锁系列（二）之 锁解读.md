# MySQL锁系列（二）之 锁解读

 时间 2017-06-05 21:25:05  Focus on MySQL

原文[http://keithlan.github.io/2017/06/05/innodb_locks_show_engine/][2]



## 背景

1. 锁系列第一期的时候介绍的锁，我们要如何去解读呢？
1. 在哪里能够看到这些锁？

## 锁信息解读

工欲善其事必先利其器

show engine innodb status 关于锁的信息是最详细的

## 案例一（有索引的情况）

* 前期准备

```sql

    dba:lc_3> show create table a;
    +-------+---------------------------------------------------------+
    | Table | Create Table |
    +-------+------------------------------------------------------------+
    | a     | CREATE TABLE `a` (
     `a` int(11) NOT NULL,
     `b` int(11) DEFAULT NULL,
     `c` int(11) DEFAULT NULL,
     `d` int(11) DEFAULT NULL,
     PRIMARY KEY (`a`),
     UNIQUE KEY `idx_b` (`b`),
     KEY `idx_c` (`c`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 |
    +-------+------------------+
    1 row in set (0.00 sec)
    
    dba:lc_3> select * from a;
    +---+------+------+------+
    | a |    b |    c |    d |
    +---+------+------+------+
    | 1 |    3 |    5 |    7 |
    | 3 |    5 |    7 |    9 |
    | 5 |    7 |    9 |   11 |
    | 7 |    9 |   11 |   13 |
    +---+------+------+------+
    4 rows in set (0.00 sec)
```

* 产生锁的语句

```sql
    dba:lc_3> set tx_isolation = 'repeatable-read';  --事务隔离级别为repeatable-read，以后介绍
    Query OK, 0 rows affected (0.00 sec)
    
    begin;
    select * from a where c=7 for update;
```

* `show engine innodb status`

```
    ------------
    TRANSACTIONS
    ------------
    Trx id counter 133588132
    Purge done for trx's n:o < 133588131 undo n:o < 0 state: running but idle
    History list length 836
    LIST OF TRANSACTIONS FOR EACH SESSION:
    ---TRANSACTION 421565826149088, not started
    0 lock struct(s), heap size 1136, 0 row lock(s)
    ---TRANSACTION 133588131, ACTIVE 8 sec
    4 lock struct(s), heap size 1136, 3 row lock(s)
    MySQL thread id 116, OS thread handle 140001238423296, query id 891 localhost dba cleaning up
    TABLE LOCK table `lc_3`.`a` trx id 133588131 lock mode IX
    RECORD LOCKS space id 281 page no 5 n bits 72 index idx_c of table `lc_3`.`a` trx id 133588131 lock_mode X
    Record lock, heap no 3 PHYSICAL RECORD: n_fields 2; compact format; info bits 0
     0: len 4; hex 80000007; asc     ;;
     1: len 4; hex 80000003; asc     ;;
    
    RECORD LOCKS space id 281 page no 3 n bits 72 index PRIMARY of table `lc_3`.`a` trx id 133588131 lock_mode X locks rec but not gap
    Record lock, heap no 3 PHYSICAL RECORD: n_fields 6; compact format; info bits 0
     0: len 4; hex 80000003; asc     ;;
     1: len 6; hex 000007f66444; asc     dD;;
     2: len 7; hex fc0000271d011d; asc    ' ;;
     3: len 4; hex 80000005; asc     ;;
     4: len 4; hex 80000007; asc     ;;
     5: len 4; hex 80000009; asc     ;;
    
    RECORD LOCKS space id 281 page no 5 n bits 72 index idx_c of table `lc_3`.`a` trx id 133588131 lock_mode X locks gap before rec
    Record lock, heap no 4 PHYSICAL RECORD: n_fields 2; compact format; info bits 0
     0: len 4; hex 80000009; asc     ;;
     1: len 4; hex 80000005; asc     ;;
```

* `show engine innodb status` 解读

```
    * Trx id counter 133588132
    
    描述的是：下一个事务的id为133588132
    
    * Purge done for trx's n:o < 133588131 undo n:o < 0 state: running but idle
    
    Purge线程已经将trxid小于133588131的事务都purge了，目前purge线程的状态为idle   
    Purge线程无法控制  
    
    * History list length 836
    
    undo中未被清除的事务数量，如果这个值非常大，说明系统来不及回收undo，需要人工介入了。  
    
    疑问：上面的purge都已经刷新完了，为什么History list length 不等于0，这是一个有意思的问题  
    
    * ---TRANSACTION 133588131, ACTIVE 8 sec
    
    当前事务id为133588131  
    
    * 4 lock struct(s), heap size 1136, 3 row lock(s)
    
    产生了4个锁对象结构，占用内存大小1136字节，3条记录被锁住(1个表锁，3个记录锁)  
    
    * TABLE LOCK table `lc_3`.`a` trx id 133588131 lock mode IX
    
    在a表上面有一个表锁，这个锁的模式为IX（排他意向锁）  
    
    * RECORD LOCKS space id 281 page no 5 n bits 72 index idx_c of table `lc_3`.`a` trx id 133588131 lock_mode X  
    
    在space id=281（a表的表空间），page no=5的页上，对表a上的idx_c索引加了记录锁，锁模式为：next-key 锁（这个在上一节中有告知）  
    该页上面的位图锁占有72bits  
    
    * 具体锁了哪些记录  
    
    Record lock, heap no 3 PHYSICAL RECORD: n_fields 2; compact format; info bits 0   -- heap no 3 的记录被锁住了
     0: len 4; hex 80000007; asc     ;;  --这是一个二级索引上的锁，7被锁住
     1: len 4; hex 80000003; asc     ;;  --二级索引上面还会自带一个主键，所以主键值3也会被锁住
    
    RECORD LOCKS space id 281 page no 3 n bits 72 index PRIMARY of table `lc_3`.`a` trx id 133588131 lock_mode X locks rec but not gap（这是一个记录锁，在主键上锁住的）  
    Record lock, heap no 3 PHYSICAL RECORD: n_fields 6; compact format; info bits 0
     0: len 4; hex 80000003; asc     ;;  --第一个字段是主键3，占用4个字节，被锁住了
     1: len 6; hex 000007f66444; asc     dD;;  --该字段为6个字节的事务id，这个id表示最近一次被更新的事务id
     2: len 7; hex fc0000271d011d; asc    '   ;; --该字段为7个字节的回滚指针，用于mvcc
     3: len 4; hex 80000005; asc     ;;  --该字段表示的是此记录的第二个字段5
     4: len 4; hex 80000007; asc     ;;  --该字段表示的是此记录的第三个字段7
     5: len 4; hex 80000009; asc     ;;  --该字段表示的是此记录的第四个字段9
    
    RECORD LOCKS space id 281 page no 5 n bits 72 index idx_c of table `lc_3`.`a` trx id 133588131 lock_mode X locks gap before rec
    Record lock, heap no 4 PHYSICAL RECORD: n_fields 2; compact format; info bits 0
     0: len 4; hex 80000009; asc     ;; --这是一个二级索引上的锁，9被锁住
     1: len 4; hex 80000005; asc     ;; --二级索引上面还会自带一个主键，所以主键值5被锁住
```

## 案例二(无索引的情况)

* 前期准备

```sql
    dba:lc_3> show create table t;
    +-------+------------------------------------------------------------------------------------+
    | Table | Create Table |
    +-------+------------------------------------------------------------------------------------+
    | t     | CREATE TABLE `t` (
     `i` int(11) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 |
    +-------+------------------------------------------------------------------------------------+
    1 row in set (0.00 sec)
    
    dba:lc_3> select * from t;
    +------+
    | i |
    +------+
    |    1 |
    |    2 |
    |    3 |
    |    4 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    |    5 |
    | 5 |
    +------+
    16 rows in set (0.00 sec)
```

* 产生锁语句

```sql
    dba:lc_3> set tx_isolation = 'repeatable-read';
    Query OK, 0 rows affected (0.00 sec)
    
    dba:lc_3> select * from t where i=1 for update;
    +------+
    | i |
    +------+
    | 1 |
    +------+
    1 row in set (0.00 sec)
```

* `show engine innodb status`

```
    ------------
    TRANSACTIONS
    ------------
    Trx id counter 133588133
    Purgedonefortrx's n:o < 133588131 undo n:o < 0 state: running but idle
    History list length 836
    LIST OF TRANSACTIONS FOR EACH SESSION:
    ---TRANSACTION 421565826149088, not started
    0 lock struct(s), heap size 1136, 0 row lock(s)
    ---TRANSACTION 133588132, ACTIVE 6 sec
    2 lock struct(s), heap size 1136, 17 row lock(s)
    MySQL thread id 118, OS thread handle 140001238955776, query id 904 localhost dba cleaning up
    TABLE LOCK table `lc_3`.`t` trx id 133588132 lock mode IX
    RECORD LOCKS space id 278 page no 3 n bits 88 index GEN_CLUST_INDEX of table `lc_3`.`t` trx id 133588132 lock_mode X
    Record lock, heap no 1 PHYSICAL RECORD: n_fields 1; compact format; info bits 0
     0: len 8; hex 73757072656d756d; asc supremum;;
    
    Record lock, heap no 2 PHYSICAL RECORD: n_fields 4; compact format; info bits 0
     0: len 6; hex 0000000dff05; asc       ;;
     1: len 6; hex 000007f66397; asc     c ;;
     2: len 7; hex fb0000271c0110; asc    '   ;;
     3: len 4; hex 80000001; asc     ;;
    
    Record lock, heapno 3PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff06; asc       ;;
     1: len 6; hex 000007f663ea; asc     c ;;
     2: len 7; hex bb000027340110; asc    '4  ;;
     3: len 4; hex 80000002; asc     ;;
    
    Record lock, heapno 4PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff07; asc       ;;
     1: len 6; hex 000007f66426; asc     d&;;
     2: len 7; hex e4000040210110; asc    @!  ;;
     3: len 4; hex 80000003; asc     ;;
    
    Record lock, heapno 5PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff08; asc       ;;
     1: len 6; hex 000007f66427; asc     d';;
     2: len 7; hex e5000040220110; asc    @"  ;;
     3: len 4; hex 80000004; asc     ;;
    
    Record lock, heapno 6PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff09; asc       ;;
     1: len 6; hex 000007f6642c; asc     d,;;
     2: len 7; hex e8000040230110; asc    @#  ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 7PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0a; asc       ;;
     1: len 6; hex 000007f6642d; asc     d-;;
     2: len 7; hex e9000040240110; asc    @$  ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 8PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0b; asc       ;;
     1: len 6; hex 000007f66432; asc     d2;;
     2: len 7; hex ec0000273f0110; asc    '?  ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 9PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0c; asc       ;;
     1: len 6; hex 000007f66433; asc     d3;;
     2: len 7; hex ed000040020110; asc    @   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 10PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0d; asc       ;;
     1: len 6; hex 000007f66434; asc     d4;;
     2: len 7; hex ee000040030110; asc    @   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 11PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0e; asc       ;;
     1: len 6; hex 000007f66435; asc     d5;;
     2: len 7; hex ef000040040110; asc    @   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 12PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff0f; asc       ;;
     1: len 6; hex 000007f66436; asc     d6;;
     2: len 7; hex f0000040050110; asc    @   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 13PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff10; asc       ;;
     1: len 6; hex 000007f66437; asc     d7;;
     2: len 7; hex f1000040060110; asc    @   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 14PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff11; asc       ;;
     1: len 6; hex 000007f66438; asc     d8;;
     2: len 7; hex f2000027130110; asc    '   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 15PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff12; asc       ;;
     1: len 6; hex 000007f66439; asc     d9;;
     2: len 7; hex f3000027140110; asc    '   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 16PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff13; asc       ;;
     1: len 6; hex 000007f6643a; asc     d:;;
     2: len 7; hex f4000027150110; asc    '   ;;
     3: len 4; hex 80000005; asc     ;;
    
    Record lock, heapno 17PHYSICAL RECORD: n_fields4; compact format; info bits 0
     0: len 6; hex 0000000dff14; asc       ;;
     1: len 6; hex 000007f6643b; asc     d;;;
     2: len 7; hex f5000027160110; asc    '   ;;
     3: len 4; hex 80000005; asc     ;;
```

* 锁解读

```
    1. 这里只列出跟第一个案例不同的地方解读，其他的都一样
    
    2. RECORD LOCKS space id 278 page no 3 n bits 88 index GEN_CLUST_INDEX of table `lc_3`.`t` trx id 133588132 lock_mode X
    
        由于表定义没有显示的索引，而InnoDB又是索引组织表，会自动创建一个索引，这里面叫index GEN_CLUST_INDEX  
    
    3. 由于没有索引，那么会对每条记录都加上lock_mode X （next-key lock）
    
    4. 这里有一个明显不一样的是：
    	Record lock, heap no 1 PHYSICAL RECORD: n_fields 1; compact format; info bits 0
    	 0: len 8; hex 73757072656d756d; asc supremum;;
    
    supremum 值得是页里面的最后一条记录(伪记录，通过select查不到的,并不是真实的记录)，heap no=1 , Infimum 表示的是页里面的第一个记录（伪记录） 
    
    可以简单的认为：
    	supremum 为upper bounds，正去穷大
    	Infimum 为Minimal bounds，负无穷大  
    
    那这里的加锁的意思就是：通过supremum 锁住index GEN_CLUST_INDEX的最大值到正无穷大的区间，这样就可以锁住全部记录，以及全部间隙，相当于表锁
```

## 锁开销

* 锁10条记录和锁1条记录的开销是成正比的吗？

```
    1. 由于锁的内存对象针对的是页而不是记录，所以开销并不是非常大  
    2. 锁10条记录和锁1条记录的内存开销都是一样的，都是heap size=1136个字节
```

## 最后

这里面`select * from a where c=7 for update;` 明明只锁一条记录，为什么却看到4把锁呢？

看到这里是不是有点晕，没关系，这个问题，后面会慢慢揭晓答案


[2]: http://keithlan.github.io/2017/06/05/innodb_locks_show_engine/
