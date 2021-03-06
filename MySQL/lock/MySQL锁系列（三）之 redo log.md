# MySQL锁系列（三）之 redo log

 时间 2017-06-12 17:29:00  Focus on MySQL

原文[http://keithlan.github.io/2017/06/12/innodb_locks_redo/][1]



## WHY

* 1）锁和事务是分不开的，事务和redo又是傻傻分不清楚的
* 2）事务最重要的是什么？ACID 又是如何实现的？

```
    * A 原子性
    	通过redo实现
    
    * C 一致性
    	通过undo实现  	
    
    * I 隔离性
    	通过lock实现
    
    * D 持久性
    	通过redo和undo实现
```

## redo log 是什么

* redo 概念

```
    * 学名：重做日志  
    
    * 个人理解：任何事务的操作都会记录redo日志，InnoDB引擎独有,用于恢复数据库到宕机的位置
```

* redo 结构

```
    * redo log  buffer
    
    	1. 日志会先写到redo log buffer ，根据制定条件刷新到redo log file
    	2. 由log block组成  
    	3. 每个log block 512字节，所以不需要double write，因为每次刷新都是原子的  	
    
    * redo log file
    
    	1. redo log的物理文件，一般有2个,大小可配置  
    	2. 由innodb_log_file_size配置，越大越好  
    	3. redo类型是物理逻辑日志，记录的是对页的操作，页内是逻辑的内容，我们姑且认为就是物理日志好了，记录的是对页的操作  
    		比如：insert 一条记录，那么大致内容就会记录  
    			对space id=3，page no=4 ，offset aa, 日志内容xx （主键索引）
    			对space id=3，page no=8 ，offset bb, 日志内容yy （二级索引）
    
```

* redo log buffer刷新触发条件

```
    1. 每秒刷新一次
    2. redo log buffer使用大于1/2进行刷新
    3. 事务commit(提交)时候进行刷新
    	a. innodb_flush_log_at_trx_commit=0 : 事务提交时，不刷新redo log buffer  
    	b. innodb_flush_log_at_trx_commit=1 : 事务提交时，将redo log buffer刷新到磁盘(由于redo没有O_direct，也必须经过操作系统缓存，然后fsync到磁盘)  
    	c. innodb_flush_log_at_trx_commit=2 : 事务提交时，将reod log buffer刷新到os的缓存
```

* redo log 的重要组成部分

```
    1. redo log 是循环写入的，写完一个，写另一个  
    2. redo log 的第一个文件的头部，会记录两个512字节的记录，分别是：checkpoint1 和 checkpoint2，轮询写入，互为备份  
    3. 上面说的很重要，checkpoint 是写在redo 日志里面的，checkpoint是什么，后面介绍  
    4. redo 里面记录的是日志的写入，里面有个很重要的概念叫做 lsn
```

* lsn 是什么

```
    * LSN = log sequeuce number , 日志的序列号，数据库的逻辑时钟, 特点是单调递增
    
    LSN表示事务写入重做日志的字节总量        
    
    * LSN是什么？有什么含义？
    
    ---
    LOG
    ---
    Log sequence number 86594404775   -- redo log buffer 的lsn，存放在redo log buffer 中 我们称： redo_mem_lsn
    Log flushed up to   86594404775   -- redo log file 的lsn，存放在redo log 中  我们称： redo_log_lsn
    Pages flushed up to 86594404775   -- 最后一个被刷新页的newest_modification, 这个用的比较少，暂时忽略, 这个存放在date page里面   
    Last checkpoint at  86594404766   -- checkpoint的lsn , 存放在redo log第一个文件的头部   ， 我们称： cp_lsn
    
    目前看下来lsn有三个含义  
    
    1. redo_mem_lsn
    2. redo_log_lsn
    3. cp_lsn
    4. page_lsn: 每个page里面头部都会记录一个lsn，表示该page最后一次被修改的redo log lsn  
    
    以上4个lsn都是互相关联的   
    
    * LSN 有什么用？
    
    	主要用于MySQL重启恢复  
    
    * 恢复的算法如下？
    
    	假设： redo_log_lsn = 15000 , cp_lsn=10000 , 这时候MySQL crash了，重启后的恢复流程如下：
    
    	a. cp_lsn = 10000 之前的redo 日志，不需要恢复： 因为checkpoint之前的日志已经可以确保刷新完毕  
    
    	b. 那么 10000 <=  redo_log_LSN <= 15000 的日志需要结合page_lsn判断，哪些需要重做，哪些不需要重做。  
    		b.1  redo_log_LSN 日志里面记录的page 操作，如果redo_log_LSN <= page_lsn   , 这些日志不需要重做，因为page已经是最新的  
    		b.2  redo_log_LSN 日志里面记录的page 操作, 如果redo_log_LSN >= page_lsn   , 这些日志是需要应用到page 里面去的，这一系列操作我们称为恢复. 
    	
    	c. 举个例子
    		如果：redo_log_lsn 11000 , 记录的是：space id=3，page no=4 的页的操作，但是这个页的page_lsn = 11500，那么说明这个页的lsn比redo的lsn新，那么就不需要应用  
    		如果：redo_log_lsn 11000 , 记录的是：space id=3，page no=4 的页的操作，但是这个页的page_lsn = 10500，那么说明这个页的lsn比redo的lsn老，那么需要应用这部分日志以达到恢复的目的
```

## Write-Ahead Log (WAL)

    当一个数据页被刷新时，必须要求内存中小于该数据页lsn对应的所有redo日志，都必须先刷新到磁盘  
    我们称为：日志先行, 保证redo log 日志必须先于 data page 刷新
    

## Force-log-at-commit

    当一个事务进行commit的时候，必须先将该事务的所有日志写入到重做日志文件进行持久化
    

## checkpoint

* 什么是checkpoint

```
    * 没有checkpoint的时候，数据库脏页都存放在内存中，如果这时候数据库挂了，那么redo就需要从头到尾开始恢复，非常慢  
    * 有checkpoint的时候，会按照一定的算法进行data page脏页的刷新， 减少数据库恢复的时间    
    	a）checkpoint_lsn 表示： 在checkpoint_lsn 之前的redo日志对应的脏页都已经刷新到磁盘了  
    	b) 也就意味着，当数据库重启恢复的时候，小于checkpoint_lsn的redo日志不需要再重做，大大的减少了数据库的恢复时间
```

* checkpoint种类

```
    * sharp checkpoint
    
    当MySQL正常关闭的时候，需要将所有的脏页都刷新  
    
    * fuzzy checkpoint
    
    为了考虑数据库的性能，MySQL按照一定算法之刷新部分脏页
```

## fuzzy checkpoint 触发条件

* 定时刷新

```
    每10秒，或者每1秒，从脏页列表中去刷新部分脏页
```

* LRU列表的刷新

```
    * buffer pool 内存解释：
    
    	* free list ： 表示数据库开启时候，MySQL会分配空闲的页给free list  
    
    	* LRU list ： 当页被第一次访问（读或者写）的时候，会加入到LRU list  
    
    	* flush list ：当页变成脏页的是，会按照第一次被更新的时间（oldest_modification）排序，加入到flush list，flush list里面存放的都是指向lru_list的指针，并不占用太多内存     
    
    * 刷新
    
    	当buffer pool中少于innodb_lru_scan_depth指定的空闲页时候  
    	会将LRU list中尾端的页（不常用的页）进行拿来用，如果是脏页，则进行checkpoint
```

* 高水位和低水位的刷新

```
    * checkpoint age = redo_lsn - cp_lsn
    
    	低水位 = 75% * 总redo大小
    	高水位 = 90% * 总redo大小
    
    * 低水位  >=  checkpoint age
    
    	不需要刷新
    
    * 低水位  <= checkpoint age <= 高水位
    
    	会强制进行 checkpoint ， 根据flush_list的顺序，刷新足够多的脏页，让checkpoint age 小于低水位线   
    
    * 高水位  >=  checkpoint age
    
    	会强制进行 checkpoint ， 根据flush_list的顺序，刷新脏页, 让其满足 低水位  <= checkpoint age >= 高水位
```

* 脏页太多的时候刷新

```
    * innodb_max_dirty_pages_pct
    
    当脏页数量超过这个比例时候，会强制进行checkpoint
```

## redo 的写入时机

* binlog的写入时机

```
    事务结束后，binlog进行写入并刷新 
    
    T1 -> T2 -> T3 -> T4  按照事务的顺序执行
```

* redo的写入时机

```
    当事务开启后，第一条dml语句开始执行时，就开始慢慢的写入并刷新redo  
    
    T1_1 -> T2_1-> T2_2-> T3_1-> T1_2-> *T2-> *T3-> *T1  
    
    以上列表的分析为：
    	这个代表事务的开始执行顺序是： T1，T2，T3
    	这个代表事务的结束执行顺序是： T2，T3，T1
```

* commit执行的时间长短，取决于什么？

```
    根据以上binlog和redo 的写入时机可以判断，commit的长短取决于binlog的日志大小和刷新时间
```

## redo 和 undo

    1. 这里先稍微提一下undo，至于undo 是什么，后面介绍  
    2. undo日志本身也要写入到redo里面去，这一点非常重要
    

## 最后

这里简单的介绍了redo的内容，这块内容非常重要，复制，高可用与之非常密切

这里面的checkpoint age可以用来监控，监控这个的目的通过这篇文章我想大家都应该知道了吧


[1]: http://keithlan.github.io/2017/06/12/innodb_locks_redo/