<?php
use \Yaf\Controller_Abstract as Controller;
use Baidu_AipOcr as AipOcr;
/**
 * @name IndexController
 * @author lover-pc\lover
 * @desc 默认控制器
 * @see http://www.php.net/manual/en/class.yaf-controller-abstract.php
 */
class IndexController extends Controller
{
	
	private static $bankCardType = [
		0 => '不能识别',
		1 => '借记卡',
		2 => '信用卡'
	];
	
	private static $languageList = [
		-1 => '未定义',
		0 => '中文',
		1 => '英文',
		2 => '日语',
		3 => '韩语'
	];
	
	private static $colorEn2ChPicker = [
		'blue' => '蓝色',
		'yellow' => '黄色'
	];
	
	private static $detectDirectionList = [
		-1 => '未定义',
		0 => '正常方向',
		1 => '逆时针旋转90',
		1 => '逆时针旋转180',
		1 => '逆时针旋转270',
	];
	
	// 身份证件识别图片状态
	private static $imgStatusList = [
		'normal' => '识别正常',
		'reversed_side' => '未摆正身份证',
		'non_idcard' => '上传的图片中不包含身份证',
		'blurred' => '身份证模糊',
		'over_exposure' => '身份证关键字段反光或过曝',
		'unknown' => '未知状态'
	];
	
	// 身份证类型
	private static $idCardTypeList = [
		'normal' => '正常身份证',
		'copy' => '复印件',
		'temporary' => '临时身份证',
		'screen' => '翻拍',
		'unknown' => '其他未知情况'
	];
	
	
	
	public function indexAction()
	{
        return TRUE;
	}
	
	public function uploadMaterialAction()
	{
		try {
			$post = $_POST;
			$materialFile = $_FILES['material'];
			if (empty($materialFile['tmp_name'])) throw new Exception('缺少材料文件');
			$filePath = APP_PATH."/public/upload/" . $materialFile["name"];
			$uploadRes = move_uploaded_file($materialFile["tmp_name"],$filePath);
			if (!$uploadRes) throw new Exception('上传材料文件失败');
			$aip = $this->getAipOcr();
			$opts = $this->parseOpts();
			$img = file_get_contents($filePath);
			$data = null;
			$dataStr = '';
			switch ((int)$post['material']) {
				case 1:
					$data = $aip->basicGeneral($img, $opts);
					if (!empty($data) && isset($data['words_result'])) {
						foreach ($data['words_result'] as $k => $v) {
							!empty($v) && $dataStr .= $v['words'].'<br />';
						}
						$dataStr .= $this->getExts($data);
					}
					break;
				case 2:
					$data = $aip->idcard($img, $post['id_card_side'], $opts);
					$dataStr = $this->parseDataStr($data);
					break;
				case 3:
					$data = $aip->bankcard($img);
					if (!empty($data) && isset($data['result'])) {
						$dataStr = '所属银行: '.$data['result']['bank_name'].
								   '<br />银行卡号: '.$data['result']['bank_card_number'].
								   '<br />所属类型: '.self::$bankCardType[$data['result']['bank_card_type']];
					}
					break;
				case 4:
					$data = $aip->drivingLicense($img, $opts);
					$dataStr = $this->parseDataStr($data);
					break;
				case 5:
					$data = $aip->vehicleLicense($img, $opts);
					$dataStr = $this->parseDataStr($data);
					break;
				case 6:
					$data = $aip->licensePlate($img, $opts);
					if (!empty($data) && isset($data['words_result'])) {
						if (isset($data['words_result']['color'])) {
							$data['words_result'][0]['color'] = $data['words_result']['color'];
							$data['words_result'][0]['number'] = $data['words_result']['number'];
							unset($data['words_result']['color']);
							unset($data['words_result']['number']);
							unset($data['words_result']['probability']);
							unset($data['words_result']['vertexes_location']);
						}
						foreach ($data['words_result'] as $v) {
							if (isset(self::$colorEn2ChPicker[$v['color']])) {
								$v['color'] = self::$colorEn2ChPicker[$v['color']];
							}
							$dataStr .= '颜色: '.$v['color'].'  车牌号: '.$v['number'].'<br />';
						}
						$dataStr .= $this->getExts($data);
					}
					break;
				case 7:
					$data = $aip->businessLicense($img);
					$dataStr = $this->parseDataStr($data);
					break;
				case 8:
					$data = $aip->receipt($img, $opts);
					if (!empty($data) && isset($data['words_result'])) {
						foreach ($data['words_result'] as $k => $v) {
							!empty($v) && $dataStr .= $v['words'].'<br />';
						}
						$dataStr .= $this->getExts($data);
					}
					break;
				default: 
					throw new Exception('未知选项');
			}
			if (isset($filePath)) @unlink($filePath);
			$this->getError($data);
			echo $this->json(200, '查询成功', $dataStr);
		} catch (Exception $e) {
			if (isset($filePath)) @unlink($filePath);
			echo $this->json(500, $e->getMessage());
		}
		return FALSE;
	}
	
	private function getAipOcr()
	{
		$conf = Yaf\Registry::get('config')->get('sdk.baidu.app')->toArray();
		return new AipOcr($conf['id'], $conf['key'], $conf['serect_key']);
	}
	
	private function parseOpts()
	{
		$opts = $_POST;
		if (!empty($opts)) {
			unset($opts['material']);
			unset($opts['id_card_side']);
			return $opts;
		} else {
			return null;
		}
	}

	private function getAttr($data, $staticAttrName, $attrName)
	{
		if (empty($data) || empty($staticAttrName) || empty($attrName)) return null;
		if (isset($data[$attrName]) && isset(self::$$staticAttrName[$data[$attrName]])) {
			
			return self::$$staticAttrName[$data[$attrName]];
		}
		return null;
	}
	
	private function getError($data)
	{
		if (isset($data['error_code'])) {
			throw new Exception('错误代码: '.$data['error_code'].'<br />具体原因: '.$data['error_msg']);
		}
	}
	
	private function parseDataStr($data)
	{
		$dataStr = '';
		if (!empty($data) && isset($data['words_result'])) {
			foreach ($data['words_result'] as $k => $v) {
				$dataStr .= $k.': '.(!empty($v['words'])?$v['words']:'暂无').'<br />';
			}
			$dataStr .= $this->getExts($data);
		}
		
		return $dataStr;
	}
	
	private function getExts($data)
	{
		$lists = [
			'语言类型' => $this->getAttr($data, 'languageList', 'language'),
			'图像朝向' => $this->getAttr($data, 'detectDirectionList', 'direction'),
			'图片状态' => $this->getAttr($data, 'imgStatusList', 'image_status'),
			'证件类型' => $this->getAttr($data, 'idCardTypeList', 'risk_type'),
		];
		$extStr = '';
		
		$lists = array_filter($lists);
		if (!empty($lists)) {
			$extStr = '<br />附加信息';
			foreach ($lists as $k => $v) {
				$extStr .= '<br />'.$k.': '.$v;
			}
		}
		
		return $extStr;
	}
	
	private function json($code = 500, $msg = 'the internet error', $data = null)
	{
		return json_encode([
			'code' => $code,
			'msg' => $msg,
			'data' => $data
		]);
	}

	
}
