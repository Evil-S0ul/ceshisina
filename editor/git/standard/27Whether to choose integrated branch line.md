# `发生过程`

wuzedong_spider, wuzedong_outlets_40 没有耦合度的两个分支被合到 wuzedong_spider_group_4.1


## 发生描述：

1. 两个功能分支测试完成

2. 合并没有耦合度的分支到独立分支.

3. 该独立分支同步到232进行两个功能测试,省去独立功能分支切换都要同步的步骤.

4. 该独立分支被推到远端.临时作为代码的阶段性保存.


# `危害`

## 1.危害范围

1. 日常开发
 1. 从【集成分支流程】来说，破坏了分支本身的角色，
 2. 从【功能分支流程】来说，增加了分支的耦合，增加代码风险
 3. 开发测试阶段如果出现 BUG，不方便排查
2. 代码历史可读性

## 2.危害认识

1. 日常开发
 1. 两个相互独立，无耦合度的分支被合并到第三个分支。
 2. 如果第三个分支即将上线，出现代码冲突，很容易影响代码稳定性，影响代码上线时间。
 3. 本来相互独立的，无耦合度的分支被合到一起，如果突然发现其中一个子分支有问题，需要修改 BUG,另外的分支就需要等待，影响代码的上线时间。
2. 如果出现 BUG，不方便排查
 1. 两个分支被合并到一起，上线后出现 BUG，不方便排查错误。
 2. 破坏了功能分支的独立性, 增加了耦合性.

# `解决方案`

## 操作

1. 删掉远程分支wuzedong_spider_group_4.1

2. 如果没有耦合度的两个分支上线，选择分支的独立分支上线流程。

    1. 独立分支测试完成后，分别上线两个分支。

3. 如果几个分支间不相互独立，有耦合度，那么就选择集成分支上线流程。

    1. 两个独立分支自测完毕后，合并到集合分支 {integration}_{功能}

    2. 测试同事 测试集合分支完毕。

    3. 用集合分支上线。

## 原则

1. 一个分支只做一件事情。

2. 没有耦合度的多个分支上线，不创建集成分支。

3. 没有耦合度的多个分支上线，走分支独立上线流程。

# `相关资料链接`

发布流程总揽图:http://dev45.gitlab.miyabaobei.com/git/demo/wikis/process_of_release

创建分支(功能、发布、紧急修复、集成, etc.):http://dev45.gitlab.miyabaobei.com/git/demo/wikis/How-To-Create-Branch

更多信息:http://dev45.gitlab.miyabaobei.com/git/demo/wikis/home