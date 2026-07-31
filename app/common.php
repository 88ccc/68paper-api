<?php
// 应用公共文件

/**
 * 判断目标字符串是否存在于逗号分隔的列表字符串中
 *
 * @param string $listStr 逗号分隔的列表字符串（如"doc,docx,pdf,ppt"）
 * @param string $target  要检查的目标字符串（如"doc"）
 * @return bool           存在返回true，不存在返回false
 */
function isInCommaSeparatedList(string $listStr, string $target): bool
{
    // 1. 清洗输入：去除两端空白，避免空白字符干扰判断
    $cleanListStr = trim($listStr);
    $cleanTarget = trim($target);

    // 2. 处理边界情况：目标为空或列表为空，直接返回false
    if (empty($cleanTarget) || empty($cleanListStr)) {
        return false;
    }

    // 3. 将逗号分隔的字符串分割为数组
    $items = explode(',', $cleanListStr);

    // 4. 清洗数组中每个元素的两端空白（处理类似" doc , pdf "的情况）
    $cleanItems = array_map(function ($item) {
        return trim($item);
    }, $items);

    // 5. 严格判断目标是否在数组中（第三个参数true开启严格类型比较）
    return in_array($cleanTarget, $cleanItems, true);
}

function getNonceStr(int $len)
{
    static $characters = '023456789ABCDEFGHJKLMNOPQRSTUVWXYZ';
    if ($len <= 0) {
        $len = 32;
    }
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $len; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
