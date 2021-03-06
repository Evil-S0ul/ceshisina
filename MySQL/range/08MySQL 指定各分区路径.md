# [MySQL 指定各分区路径][0]

### 介绍 

可以针对分区表的每个分区指定各自的存储路径，对于innodb存储引擎的表只能指定数据路径，因为数据和索引是存储在一个文件当中，对于MYISAM存储引擎可以分别指定数据文件和索引文件，一般也只有RANGE、LIST分区、sub子分区才有可能需要 单独指定各个分区的路径，HASH和KEY分区的所有分区的路径都是一样。RANGE分区指定路径和LIST分区是一样的，这里就拿LIST分区来做讲解。

### 一、MYISAM存储引擎

```sql
    CREATE TABLE th (id INT, adate DATE)
    engine='MyISAM'
    PARTITION BY LIST(YEAR(adate))
    (
      PARTITION p1999 VALUES IN (1995, 1999, 2003)
        DATA DIRECTORY = '/data/data'
        INDEX DIRECTORY = '/data/idx',
      PARTITION p2000 VALUES IN (1996, 2000, 2004)
        DATA DIRECTORY = '/data/data'
        INDEX DIRECTORY = '/data/idx',
      PARTITION p2001 VALUES IN (1997, 2001, 2005)
        DATA DIRECTORY = '/data/data'
        INDEX DIRECTORY = '/data/idx',
      PARTITION p2002 VALUES IN (1998, 2002, 2006)
        DATA DIRECTORY = '/data/data'
        INDEX DIRECTORY = '/data/idx'
    );
```

注意：MYISAM存储引擎的数据文件和索引文件是分库存储所以可以为数据文件和索引文件定义各自的路径，INNODB存储引擎只能定义数据路径。

### 二、INNODB存储引擎

```sql
    CREATE TABLE thex (id INT, adate DATE)
    engine='InnoDB'
    PARTITION BY LIST(YEAR(adate))
    (
      PARTITION p1999 VALUES IN (1995, 1999, 2003)
        DATA DIRECTORY = '/data/data',
        
      PARTITION p2000 VALUES IN (1996, 2000, 2004)
        DATA DIRECTORY = '/data/data',
       
      PARTITION p2001 VALUES IN (1997, 2001, 2005)
        DATA DIRECTORY = '/data/data',
        
      PARTITION p2002 VALUES IN (1998, 2002, 2006)
        DATA DIRECTORY = '/data/data'
      
    );
```

![][1]

指定路径之后在原来的路径中innodb生成了4个指向数据存储的路径文件，myisam生成了一个th.par文件指明该表是分区表，同时数据文件和索引文件指向了实际的存储路径。

### **三、子分区**

1.子分区

```sql
    CREATE TABLE tb_sub_dir (id INT, purchased DATE)
    ENGINE='MYISAM'
        PARTITION BY RANGE( YEAR(purchased) )
        SUBPARTITION BY HASH( TO_DAYS(purchased) ) (
            PARTITION p0 VALUES LESS THAN (1990) 
            (
                SUBPARTITION s0
                    DATA DIRECTORY = '/data/data_sub1'
                    INDEX DIRECTORY = '/data/idx_sub1',
                SUBPARTITION s1
                    DATA DIRECTORY = '/data/data_sub1'
                    INDEX DIRECTORY = '/data/idx_sub1'
            ),
            PARTITION p1 VALUES LESS THAN (2000) 
            (
                SUBPARTITION s2
                    DATA DIRECTORY = '/data/data_sub2'
                    INDEX DIRECTORY = '/data/idx_sub2',
                SUBPARTITION s3
                    DATA DIRECTORY = '/data/data_sub2'
                    INDEX DIRECTORY = '/data/idx_sub2'
            ),
            PARTITION p2 VALUES LESS THAN MAXVALUE 
            (
                SUBPARTITION s4
                    DATA DIRECTORY = '/data/data_sub3'
                    INDEX DIRECTORY = '/data/idx_sub3',
                SUBPARTITION s5
                    DATA DIRECTORY = '/data/data_sub3'
                    INDEX DIRECTORY = '/data/idx_sub3'
            )
        );
```

![][2]

**2.子分区再分**

```sql
    CREATE TABLE tb_sub_dirnew (id INT, purchased DATE)
    ENGINE='MYISAM'
        PARTITION BY RANGE( YEAR(purchased) )
        SUBPARTITION BY HASH( TO_DAYS(purchased) ) (
            PARTITION p0 VALUES LESS THAN (1990) 
            DATA DIRECTORY = '/data/data'
            INDEX DIRECTORY = '/data/idx'
            (
                SUBPARTITION s0
                    DATA DIRECTORY = '/data/data_sub1'
                    INDEX DIRECTORY = '/data/idx_sub1',
                SUBPARTITION s1
                    DATA DIRECTORY = '/data/data_sub1'
                    INDEX DIRECTORY = '/data/idx_sub1'
            ),
            PARTITION p1 VALUES LESS THAN (2000)
            DATA DIRECTORY = '/data/data'
            INDEX DIRECTORY = '/data/idx'
            (
                SUBPARTITION s2
                    DATA DIRECTORY = '/data/data_sub2'
                    INDEX DIRECTORY = '/data/idx_sub2',
                SUBPARTITION s3
                    DATA DIRECTORY = '/data/data_sub2'
                    INDEX DIRECTORY = '/data/idx_sub2'
            ),
            PARTITION p2 VALUES LESS THAN MAXVALUE
            DATA DIRECTORY = '/data/data'
            INDEX DIRECTORY = '/data/idx'
            (
                SUBPARTITION s4
                    DATA DIRECTORY = '/data/data_sub3'
                    INDEX DIRECTORY = '/data/idx_sub3',
                SUBPARTITION s5
                    DATA DIRECTORY = '/data/data_sub3'
                    INDEX DIRECTORY = '/data/idx_sub3'
            )
        );
```


也可以给个分区指定路径后再给子分区指定路径，但是这样没有意义，因为数据的存在都是由子分区决定的。

**注意：**

1.指定的路径必须存在，否则分区无法创建成功

2.MYISAM存储引擎的数据文件和索引文件是分库存储所以可以为数据文件和索引文件定义各自的路径，INNODB存储引擎只能定义数据路径

**参考：**



### **总结** 

通过给各个分区指定各自的磁盘可以有效的提高读写性能，在条件允许的情况下是一个不错的方法。

[0]: http://www.cnblogs.com/chenmh/p/5644713.html
[1]: ./img/135426-20160705183623139-903477842.png
[2]: ./img/135426-20160707114052717-259832955.png
